<?php

namespace Repository;

use Core\AccessRight\AccessRight;
use Core\ArrayHandler;
use Core\Config;
use Core\QueryBuilder;
use Exception\CustomPdoException;
use Models\Entity;
use Models\Product;
use Models\ProductUserData;
use Models\Stock;
use PDOException;
use PullRepository\PriceDatePullRepository;
use PullRepository\SameProductPullRepository;
use PullRepository\StockPullRepository;
use QueryPdo;

/**
 * @method Product find(int $id)
 * @method Product[] findByParams(array $params, array $filters = [])
 */
class ProductRepository extends Repository
{
    protected string $entityModel = Product::class;
    protected ?string $userDataRepositoryModel = ProductUserDataRepository::class;

    private BookRepository $bookRepository;
    private SourceProductRepository $sourceProductRepository;
    private StockRepository $stockRepository;
    private PriceDateRepository $priceDateRepository;
    private SameProductRepository $sameProductRepository;

    public function __construct()
    {
        parent::__construct();

        $this->bookRepository = new BookRepository();
        $this->sourceProductRepository = new SourceProductRepository();
        $this->stockRepository = new StockRepository();
        $this->priceDateRepository = new PriceDateRepository();
        $this->sameProductRepository = new SameProductRepository();
    }

    public function save(array $entityData): int
    {
        ArrayHandler::hasParamThroughException(Product::PARAM_SHOP_PRODUCT_ID, $entityData, 'shop_product_id is required');
        // Убрать проверку на shop_type, и всегда брать текущий, откуда запрос приходит.
//        ArrayHandler::hasParamThroughException(Product::PARAM_SHOP_TYPE, $entityData, 'shop_type is required');

        if (Config::isWildberriesShopType()) {
            ArrayHandler::hasParamThroughException(
                Product::PARAM_SHOP_PRODUCT_CODE,
                $entityData,
                'shop_product_code is required'
            );
        }

        $entityData[Product::PARAM_SHOP_ID] = Config::getCurrentShopId();

        $stocks = $entityData[Product::PARAM_STOCKS] ?? [];
        $priceDates = $entityData[Product::PARAM_PRICE_DATES] ?? [];
        $flags = $entityData[Product::PARAM_FLAGS] ?? [];

        $entityId = null;
        $positionPrice = null;
        $product = $this->findProduct($entityData[Product::PARAM_SHOP_PRODUCT_ID]);

        if ($product) {
            $entityId = $product->getId();
            $positionPrice = $product->getMinPrice();
            $entityData[Entity::PARAM_ID] = $entityId;
        }

        $toChangeId = ArrayHandler::hasParamTrue(Product::FLAG_TO_CHANGE_ID, $flags) && Config::isWildberriesShopType();

        /**
         * Если находит товар с не пустым shop_product_code у wildberries, значит уже заменён shop_product_id.
         * Сохранение не производим, передаваемые ид неверны.
         */
        if ($toChangeId && !$entityId) {
            $entityId = $this->tryToChangeId($entityData);

            if ($entityId) {
                return $entityId;
            }
        }

        if (ArrayHandler::hasParamTrue(Product::FLAG_TO_LINK_BOOK, $flags)) {
            if (!$entityId) {
                throw new \Exception('При привязки книги не найден товар.');
            }

            ArrayHandler::hasParamThroughException(
                Product::PARAM_BOOK,
                $entityData,
                'Не найдена книга в товаре для линка.'
            );

            $this->bookRepository->linkBookToProduct($entityId, $entityData[Product::PARAM_BOOK][Entity::PARAM_ID]);

            return $entityId;
        }

        if (ArrayHandler::hasParamTrue(Product::FLAG_TO_UNLINK_BOOK, $flags)) {
            if (!$entityId) {
                throw new \Exception('При отвязки книги не найден товар.');
            }

            $this->bookRepository->unlinkBookFromProduct($entityId);

            return $entityId;
        }

        if (ArrayHandler::hasParamTrue(Product::FLAG_TO_LINK_SOURCE_PRODUCT, $flags)) {
            if (!$entityId) {
                throw new \Exception('При привязки источника товара не найден товар.');
            }

            ArrayHandler::hasParamThroughException(
                Product::PARAM_SOURCE_PRODUCT,
                $entityData,
                'Не найден источник товара в товаре для линка.'
            );

            $this->sourceProductRepository->linkToProduct($entityId, $entityData[Product::PARAM_SOURCE_PRODUCT][Entity::PARAM_ID]);

            return $entityId;
        }

        if (ArrayHandler::hasParamTrue(Product::FLAG_TO_UNLINK_SOURCE_PRODUCT, $flags)) {
            if (!$entityId) {
                throw new \Exception('При отвязки источника товара не найден товар.');
            }

            $this->sourceProductRepository->unlinkFromProduct($entityId);

            return $entityId;
        }

        $this->setToSaveUserData(ArrayHandler::hasParamTrue(Product::FLAG_TO_SAVE_PRODUCT_USER_DATA, $flags)
            && ArrayHandler::hasParam(Product::PARAM_PRODUCT_USER_DATA, $entityData));

        $entityId = $this->processSave($entityData);

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
        } else if (!AccessRight::isAdmin()) {
            $filters[self::PARAM_LIMIT] = 100;
        }

        if (Config::isWildberriesShopType()) {
            $params[Product::PARAM_SHOP_PRODUCT_CODE] = QueryPdo::EXPR_IS_NOT_NULL;
        }

//        $this->enableDebugQuery(); // Потом убрать.
        $models = $this->findByParams($params, $filters);
        $this->addOneToManyRelationsModels($models);

        return $models;
    }

    public function findProduct(string $shopProductId, bool $addRelations = false): null|Product
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

        if ($addRelations) {
            $this->addOneToManyRelationsModels($models);
        }

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

        return $this->findByParams($params);
    }

    /**
     * @param Product[] $productModels
     */
    private function addOneToManyRelationsModels(array $productModels): void
    {
        $ids = array_map(function ($productModel) {
            return $productModel->getId();
        }, $productModels);

        $priceDatesPull = new PriceDatePullRepository($ids);
        $stocksPull = new StockPullRepository($ids);
        $sameProductsPull = new SameProductPullRepository($ids);

        array_map(function ($productModel) use ($priceDatesPull, $stocksPull, $sameProductsPull) {
            $productId = $productModel->getId();

            $productModel->setPriceDates($priceDatesPull->getFromPull($productId));
            $productModel->setStocks($stocksPull->getFromPull($productId));

            $findSameProductId = $productModel->getBook()
                ? SameProductPullRepository::BOOK_PREFIX . $productModel->getBook()->getId()
                : (
                $productModel->getSourceProduct()
                    ? SameProductPullRepository::SP_PREFIX . $productModel->getSourceProduct()->getId()
                    : null
                );

            if ($findSameProductId) {
                $productModel->setSameProducts($sameProductsPull->getFromPullSortMin($productModel, $findSameProductId));
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
        $product = $this->findProduct($data[Product::PARAM_SHOP_PRODUCT_ID]);
        if (!$product) {
            return null;
        }

//        $affectedCount = $this->changeId($product->getId(), $data[Product::PARAM_SHOP_PRODUCT_ID], $data[Product::PARAM_SHOP_PRODUCT_CODE]);

        return $product->getId();
    }
}
