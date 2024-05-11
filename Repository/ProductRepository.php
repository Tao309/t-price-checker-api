<?php

namespace Repository;

use Core\Config;
use Core\EntityDataBuilder;
use Models\Entity;
use Models\PriceDate;
use Models\Product;
use Models\Shop;
use Models\Stock;
use PDOException;
use QueryPdo;

class ProductRepository extends Repository
{
    protected string $entityModel = Product::class;

    private BookRepository $bookRepository;
    private StockRepository $stockRepository;
    private PriceDateRepository $priceDateRepository;
    private SameProductRepository $sameProductRepository;

    public function __construct()
    {
        parent::__construct();

        $this->bookRepository = new BookRepository();
        $this->stockRepository = new StockRepository();
        $this->priceDateRepository = new PriceDateRepository();
        $this->sameProductRepository = new SameProductRepository();
    }

    public function save(array $data): void
    {
        //die('Saving products is temporary unavailable.');
        //var_dump($data);exit;
        //return;

        $stocks = $data[Product::PARAM_STOCKS] ?? [];
        $dates = $data[Product::PARAM_PRICE_DATES] ?? [];
        $flags = $data[Product::PARAM_FLAGS] ?? [];

        $positionId = null;
        $positionPrice = null;
        $positionQty = null;
        $product = $this->get($data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_SHOP_TYPE]);

        if ($product) {
            $positionId = $product->getId();
            $positionPrice = $product->getMinPrice();
            $positionQty = $product->getLastQty();
        }

        $toChangeId = isset($flags[Product::FLAG_TO_CHANGE_ID]) && Config::isWildberriesShopType();

        // Если находит товар с не пустым code, значит уже заменён product_id
        if ($toChangeId && !$positionId) {
            $positionId = $this->tryToChangeId($data);

            if ($positionId) {
                return;
            }
        }

        if (isset($flags[Product::FLAG_TO_LINK_BOOK])) {
            if (!$positionId) {
                throw new \Exception('При привязки книги не найден товар.');
            }

            if (!isset($data[Product::PARAM_BOOK])) {
                throw new \Exception('Не найдена книга в товаре для линка.');
            }

            $this->bookRepository->linkBookToProduct($positionId, $data[Product::PARAM_BOOK][Entity::PARAM_ID]);
            return;
        } elseif (isset($flags[Product::FLAG_TO_UNLINK_BOOK])) {
            if (!$positionId) {
                throw new \Exception('При отвязки книги не найден товар.');
            }

            $this->bookRepository->unlinkBookFromProduct($positionId);
            return;
        }

        if (!$positionId) {
            $positionId = $this->create($data);
        } elseif (isset($flags[Product::FLAG_TO_SAVE_PRODUCT])) {
            $this->update($data);
        }

        if (!$positionId) {
            return;
        }

        if (isset($flags[Product::FLAG_TO_SAVE_PRICE_DATES])) {
            if (!($positionPrice && end($dates)['price'] > $positionPrice)) {
                $this->priceDateRepository->savePriceDates($positionId, $dates);
            }
        }

        if (isset($flags[Product::FLAG_TO_SAVE_STOCKS])) {
            if (!($positionQty && end($stocks)['qty'] == $positionQty)) {
                $this->stockRepository->saveStocks($positionId, $stocks);
            }
        }
    }

    public function removeByProductId($productId): bool
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [Product::PARAM_IS_ARCHIVE => TRUE]
            )
            ->where(Product::PARAM_PRODUCT_ID, ':product_id')
            ->where(Product::PARAM_SHOP_ID, ':shop_id')
            ->where(Product::PARAM_USER_ID, ':user_id');

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $stmt->execute([
            Product::PARAM_PRODUCT_ID => $productId,
            Product::PARAM_SHOP_ID => Config::getCurrentShopId(),
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        return true;
    }

    /**
     * @param array $productIds
     * @return Product[]
     */
    public function getProductsByProductIds(array $productIds): array
    {
        $query = $this->getListQueryNew();

        $query->where('user_id', ':user_id');
        $query->where('product_id', $productIds);
        $query->where('shop_id', ':shop_id');

        if (Config::getCurrentUserid() !== 2) {
            $query->limit(100);
        }

        if (Config::isWildberriesShopType()) {
            $query->where('code', QueryPdo::EXPR_IS_NOT_NULL);
        }

        if (Config::getCurrentUserid() !== 2) {
            $query->limit(100);
        }

        return $this->assembleQueryToModels(
            $query->fetchAll($this->getListQueryVariables(
                [Product::PARAM_SHOP_ID => Config::getCurrentShopId()]
            ))
        );
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
        $query = $this->getListQueryNew();

        if (Config::isWildberriesShopType()) {
            $query->where('code', QueryPdo::EXPR_IS_NOT_NULL);
        }

        $query->where('book_id', ':book_id');
        $query->where('shop_id', QueryPdo::EXPR_IS_NOT_NULL);

        $rows = $query->fetchAll([Product::PARAM_BOOK_ID => $bookId]);

        return $this->assembleQueryToModels($rows);
    }

    /**
     * @param array $rows
     * @return Product[]
     */
    private function assembleQueryToModels(array $rows): array
    {
        $ids = array_column($rows, Entity::PARAM_ID);

        $priceDatesData = $ids ? $this->priceDateRepository->getPriceDatesForProducts($ids) : [];
        $stocksData = $ids ? $this->stockRepository->getStocksForProducts($ids) : [];
        $sameProductDataRows = $ids ? $this->sameProductRepository->getAllSameProductsByBook($ids) : [];

        return array_map(function ($productData) use ($priceDatesData, $stocksData, $sameProductDataRows) {
            $productBookId = $productData[Product::PARAM_BOOK_ID] ?? 0;
            $productId = $productData[Entity::PARAM_ID];

            $productData[Product::PARAM_PRICE_DATES] = $priceDatesData[$productId] ?? [];
            $productData[Product::PARAM_STOCKS] = $stocksData[$productId] ?? [];
            $productData[Product::PARAM_SAME_PRODUCTS] = ($productBookId && isset($sameProductDataRows[$productBookId]))
                ? $this->sameProductRepository->prepareSameProducts(
                    $productData,
                    $sameProductDataRows[$productBookId]
                )
                : [];

            return new Product($productData);
        }, $rows);
    }

    private function getListQueryVariables(array $newVariables = []): array
    {
        $variables = [
//            'shop_id' => Config::getCurrentShopId(),
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
        ];

        return array_merge($variables, $newVariables);
    }

    protected function getEntityDataBuilder(array $data): EntityDataBuilder
    {
        $data[Product::PARAM_USER_ID] = Config::getCurrentUserid();
        $data[Product::PARAM_SHOP] = [
            Shop::PARAM_ID => Config::getShopIdByType($data['shop_type']),
            Shop::PARAM_TYPE => $data['shop_type'],
        ];

        return parent::getEntityDataBuilder($data);
    }

    private function update(array $productData): void
    {
        $entityDataBuilder = $this->getEntityDataBuilder($productData);

        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(Product::PARAM_PRODUCT_ID, ':product_id')
            ->where(Product::PARAM_SHOP_ID, ':shop_id')
            ->where(Product::PARAM_USER_ID, ':user_id');

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $variables = [
            Product::PARAM_SHOP_ID => $entityDataBuilder->getPreparedData(Product::PARAM_SHOP_ID),
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
            Product::PARAM_PRODUCT_ID => $entityDataBuilder->getEntityData(Product::PARAM_PRODUCT_ID),
        ];

        try {
            $stmt->execute($variables);

//            if (!$stmt->rowCount()) {
//                throw  new \Exception('Обновлено ' . $stmt->rowCount() . ' позиций');
//            }
        } catch(PDOException $e) {
            processPdoException('ProductRepository.update', $variables, $data, $stmt, $e);
        }
    }

    private function create(array $productData): int|null
    {
        $entityDataBuilder = $this->getEntityDataBuilder($productData);
        $entityDataBuilder->appendPreparedData([
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
            Product::PARAM_PRODUCT_ID => $entityDataBuilder->getEntityData(Product::PARAM_PRODUCT_ID),
        ]);

        $query = (new QueryPdo())
            ->insert(Product::TABLE_NAME, $entityDataBuilder->getQueryPreparedData());

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        try {
            $stmt->execute($query->getPreparedData());

            return $dbh->lastInsertId();
        } catch(PDOException $e) {
            processPdoException('ProductRepository.create',
                $entityDataBuilder->getQueryKeysVariables(), $entityDataBuilder->getQueryPreparedData(),
                $stmt, $e);

            return null;
        }
    }

    /**
     * Для товаров заменяем product_id, который раньше был как код 1С на id товара и записываем в code код 1С.
     *
     * @param string $code      Код 1С.
     * @param string $productId ID продукта.
     *
     * @return int
     */
    private function changeId(int $positionId, string $productId, string $code): int
    {
        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [
                    Product::PARAM_PRODUCT_ID => $productId,
                    Product::PARAM_CODE => $code
                ]
            )
            ->where(Product::PARAM_CODE, QueryPdo::EXPR_IS_NULL)
            ->where(Product::PARAM_ID, ':id')
            ->where(Product::PARAM_SHOP_ID, ':shop_id')
            ->where(Product::PARAM_USER_ID, ':user_id');

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query->assemble());

        $stmt->execute([
            Product::PARAM_ID => $positionId,
            Product::PARAM_SHOP_ID => Config::getCurrentShopId(),
            Product::PARAM_USER_ID => Config::getCurrentUserid(),
        ]);

        return $stmt->rowCount();
    }

    /**
     * Запуск, если не нашёлся товар по product_id и с code, в wildberries.
     *
     * @param array $data
     * @return int|null
     * @throws \Exception
     */
    private function tryToChangeId(array $data)
    {
        // По ид не найдёт, если товар без кода, в ид сейчас код установлен по старой схеме.
//        $tempPositionData = $this->getPositionData($data[Product::PARAM_CODE], $data['shop_type'], false);
        $product = $this->get($data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_SHOP_TYPE], false);
        if (!$product) {
            return null;
        }

        $affectedCount = $this->changeId($product->getId(), $data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_CODE]);

        return $product->getId();
    }

    public function get($productId, string $shopType, $withCode = true): Product|null
    {
        $shopId = Config::getShopIdByType($shopType);

        $query = $this->getListQueryNew();

        $priceDatesSubQuery = (new QueryPdo())
            ->select([PriceDate::PARAM_ID, 'MIN('.PriceDate::PARAM_PRICE.') AS price'])
            ->from(PriceDate::TABLE_NAME)
            ->group(Stock::PARAM_ID);

        $stockSubQuery = (new QueryPdo())
            ->select([Stock::PARAM_ID, Stock::PARAM_QTY])
            ->from(Stock::TABLE_NAME)
            ->order(Stock::PARAM_ID, 'DESC')
            ->group(Stock::PARAM_ID);

        $query->leftJoin(
            ['pd' => '('.$priceDatesSubQuery->assemble().')'],
            'pd.id = '.Product::TABLE_PREFIX.'.id',
            [
                'pd.price AS ' . Product::PARAM_MIN_PRICE
            ]
        )
        ->leftJoin(
            ['ps' => '('.$stockSubQuery->assemble().')'],
            'ps.id = '.Product::TABLE_PREFIX.'.id',
            [
                'ps.qty AS ' . Product::PARAM_LAST_QTY
            ]
        );

        $query->where('shop_id', ':shop_id');
        $query->where('product_id', ':product_id');
        $query->where('user_id', ':user_id');
        $query->limit(1);

        if (Config::isWildberriesShopType()) {
            if ($withCode) {
                $query->where('code', QueryPdo::EXPR_IS_NOT_NULL);
            } else {
                $query->where('code', QueryPdo::EXPR_IS_NULL);
            }
        }

        $data = $query->fetch(
            $this->getListQueryVariables(
                [
                    Product::PARAM_PRODUCT_ID => $productId,
                    Product::PARAM_SHOP_ID => $shopId,
                ]
            )
        );

        if (!$data) {
            return null;
//            throw new \Exception('Not found product data by id '. $productId . ' and shop_id ' . $shopId);
        }

        return new Product($data);
    }
}