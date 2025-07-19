<?php

namespace Repository;

use AccessRights\AccessHandler;
use Core\ArrayHandler;
use Core\Config;
use Models\Entity;
use Models\Product;
use Models\ProductUserData;
use Models\Stock;
use PullRepository\PriceDatePullRepository;
use PullRepository\SameProductByBookPullRepository;
use PullRepository\SameProductBySourceProductPullRepository;
use PullRepository\StockPullRepository;
use Query\QueryPdo;

/**
 * @method Product find(int $id)
 * @method Product[] findByParams(array $params, array $filters = [])
 */
class ProductRepository extends Repository
{
    protected string $entityModel = Product::class;
    protected ?string $userDataRepositoryModel = ProductUserDataRepository::class;

    private StockRepository $stockRepository;
    private PriceDateRepository $priceDateRepository;

    public function __construct()
    {
        parent::__construct();

        $this->stockRepository = new StockRepository();
        $this->priceDateRepository = new PriceDateRepository();
    }

    /**
     * @param array $data
     *
     * @return array|int
     *
     * @throws \Exception\CustomPdoException
     * @throws \Exception\ResponseException
     * @throws \ReflectionException
     */
    public function saveProduct(array $data): array|int
    {
        ArrayHandler::hasParamThroughException(Product::PARAM_SHOP_PRODUCT_ID, $data, 'shop_product_id is required');

        if (Config::isWildberriesShopType()) {
            ArrayHandler::hasParamThroughException(
                Product::PARAM_SHOP_PRODUCT_CODE,
                $data,
                'shop_product_code is required'
            );
        }

        $data[Product::PARAM_SHOP_ID] = Config::getCurrentShopId();

        $stocks = $data[Product::PARAM_STOCKS] ?? [];
        $priceDates = $data[Product::PARAM_PRICE_DATES] ?? [];
        $flags = $data[Product::PARAM_FLAGS] ?? [];

        $entityId = null;
        $positionPrice = null;
        $product = $this->findProduct(ArrayHandler::getValueAsString(Product::PARAM_SHOP_PRODUCT_ID, $data));

        if ($product) {
            $entityId = $product->getId();
            $positionPrice = $product->getMinPrice();
            $data[Entity::PARAM_ID] = $entityId;
        }

        $toChangeId = ArrayHandler::hasParamTrue(Product::FLAG_TO_CHANGE_ID, $flags) && Config::isWildberriesShopType();

        /**
         * Если находит товар с не пустым shop_product_code у wildberries, значит уже заменён shop_product_id.
         * Сохранение не производим, передаваемые ид неверны.
         */
        if ($toChangeId && !$entityId) {
            $entityId = $this->tryToChangeId($data);

            if ($entityId) {
                return $entityId;
            }
        }

        $this->setToSaveUserData(
            ArrayHandler::hasParamTrue(Product::FLAG_TO_SAVE_PRODUCT_USER_DATA, $flags)
            && ArrayHandler::hasParam(Product::PARAM_PRODUCT_USER_DATA, $data)
        );

        $entityId = $this->save($data);

        if (!$entityId) {
            throw new \Exception('Entity is not exists for save priceDates or stocks.');
        }

        // Если цена не уменьшилась, то приходит ошибочный запрос на добавление, сток тоже не обрабатываем.
        if (ArrayHandler::hasParamTrue(Product::FLAG_TO_SAVE_PRICE_DATES, $flags) && $priceDates) {
            if ($positionPrice && end($priceDates)['price'] >= $positionPrice) {
                return $entityId;
            }

            $this->priceDateRepository->savePriceDates($entityId, $priceDates);
        }

        $lastStock = end($stocks);
        $productLastStock = $product?->getLastStock();
        $isLastStockEqualsQty = $lastStock && $productLastStock
            && $productLastStock->getDate()->format('d.m.Y') == Config::getDateTime($lastStock[Stock::PARAM_DATE])->format('d.m.Y')
            && $productLastStock->getQty() == $lastStock[Stock::PARAM_QTY];

        if (ArrayHandler::hasParamTrue(Product::FLAG_TO_SAVE_STOCKS, $flags) && $stocks && !$isLastStockEqualsQty) {
            $this->stockRepository->saveStocks($entityId, $stocks);
        }

        return $entityId;
    }

    /**
     * Получаем массив моделей продуктов.
     *
     * @param array $shopProductIds Массив ID товаров магазина.
     *
     * @return Product[] Массив моделей продуктов.
     *
     * @throws \Exception
     */
    public function getProductsByShopProductIds(array $shopProductIds): array
    {
        // проверить как приходит пустой массив
        if (empty($shopProductIds)) {
            return [];
        }

        $params = [
            Product::PARAM_SHOP_PRODUCT_ID => $shopProductIds,
            Product::PARAM_SHOP_ID => Config::getCurrentShopId(),
            sprintf(
                '%s.%s',
                ProductUserData::TABLE_PREFIX,
                ProductUserData::PARAM_IS_ARCHIVE
            ) => false
        ];

        $filters = [];

        if (count($shopProductIds) === 1) {
            $filters[self::PARAM_LIMIT] = 1;
        } else if (AccessHandler::getAccessConfig('product.limit_enabled', true)) {
            $filters[self::PARAM_LIMIT] = AccessHandler::getAccessConfig(
                'product.limit',
                AccessHandler::VALUE_DEFAULT_PRODUCT_LIMIT
            );
        } else {
            // По умолчанию для всех, кто без лимита.
            $filters[self::PARAM_LIMIT] = 300;
        }

        if (Config::isWildberriesShopType()) {
            $params[Product::PARAM_SHOP_PRODUCT_CODE] = QueryPdo::EXPR_IS_NOT_NULL;
        }

        $models = $this->findByParams($params, $filters);
        $this->addOneToManyRelationsModels($models);

        return $models;
    }

    public function findProduct(string $shopProductId): null|Product
    {
        $models = $this->findByParams(
            [
                Product::PARAM_SHOP_PRODUCT_ID => $shopProductId,
                Product::PARAM_SHOP_ID => Config::getCurrentShopId()
            ],
            [
                self::PARAM_LIMIT => 1
            ]
        );

        // Всегда надо добавлять stocks и priceDates.
        $this->addOneToManyRelationsModels($models, false);

        return $models ? reset($models) : null;
    }

    /**
     * Получение списка продуктов по книге, используется в общем показе для всех.
     * Поиск по user_id не требуется.
     *
     * @param int $bookId
     *
     * @return Product[]
     */
    public function getProductsByBookId(int $bookId): array
    {
        $params = [
            Product::PARAM_BOOK_ID => $bookId,
            Product::PARAM_SHOP_ID => QueryPdo::EXPR_IS_NOT_NULL
        ];

        if (Config::isWildberriesShopType()) {
            $params[Product::PARAM_SHOP_PRODUCT_CODE] = QueryPdo::EXPR_IS_NOT_NULL;
        }

        $filters = [
            self::PARAM_LIMIT => 300,
        ];

        $models = $this->findByParams($params, $filters);
        $this->addOneToManyRelationsModels($models, false);

        return $models;
    }

    /**
     * @param array<Product> $productModels
     * @param bool $addSameProducts
     *
     * @return void
     */
    private function addOneToManyRelationsModels(array $productModels, bool $addSameProducts = true): void
    {
        $productIds = [];
        $bookIds = [];
        $sourceProductIds = [];

        foreach ($productModels as $productModel) {
            $productIds[] = $productModel->getId();

            if ($productModel->getBook()) {
                $bookIds[] = $productModel->getBook()->getId();
                continue;
            }

            if ($productModel->getSourceProduct()) {
                $sourceProductIds[] = $productModel->getSourceProduct()->getId();
                continue;
            }
        }

        $bookIds =  array_unique($bookIds);
        $sourceProductIds =  array_unique($sourceProductIds);

        $priceDatesPull = $productIds ? new PriceDatePullRepository($productIds) : [];
        $stocksPull = $productIds ? new StockPullRepository($productIds) : [];

        $sameProductsByBook = $bookIds && $addSameProducts ? new SameProductByBookPullRepository($bookIds) : null;
        $sameProductsBySourceProduct = $sourceProductIds && $addSameProducts
            ? new SameProductBySourceProductPullRepository($sourceProductIds)
            : null;

        array_map(function ($productModel) use (
            $priceDatesPull, $stocksPull, $sameProductsByBook, $sameProductsBySourceProduct
        ) {
            $productId = $productModel->getId();

            $productModel->setPriceDates($priceDatesPull->getFromPull($productId));
            $productModel->setStocks($stocksPull->getFromPull($productId));

            if ($productModel->getPriceDates()) {
                $productModel->setMinPrice(min(
                    array_map(function($priceDate) {
                        return $priceDate->getPrice();
                    }, $productModel->getPriceDates())
                ));
            }

            if ($productModel->getStocks()) {
                $stocks = $productModel->getStocks();
                $productModel->setLastQty(end($stocks) ? end($stocks)->getQty() : null);
            }

            if ($productModel->getBook() && !empty($sameProductsByBook)) {
                $productModel->setSameProducts(
                    $sameProductsByBook->getFromPullSortMin($productModel, $productModel->getBook()->getId())
                );

                return;
            }

            if ($productModel->getSourceProduct() && !empty($sameProductsBySourceProduct)) {
                $productModel->setSameProducts(
                    $sameProductsBySourceProduct->getFromPullSortMin(
                        $productModel,
                        $productModel->getSourceProduct()->getId()
                    )
                );

                return;
            }

        }, $productModels);
    }

    /**
     * Запуск, если не нашёлся товар по shop_product_id и с shop_product_code, в wildberries.
     *
     * @param array $data
     * @return int|null
     * @throws \Exception
     */
    private function tryToChangeId(array $data)
    {
        // По ид не найдёт, если товар без кода, в ид сейчас код установлен по старой схеме.
        $product = $this->findProduct(ArrayHandler::getValueAsString(Product::PARAM_SHOP_PRODUCT_ID, $data));
        if (!$product) {
            return null;
        }

//        $affectedCount = $this->changeId($product->getId(), $data[Product::PARAM_SHOP_PRODUCT_ID], $data[Product::PARAM_SHOP_PRODUCT_CODE]);

        return $product->getId();
    }
}
