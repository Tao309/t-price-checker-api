<?php

namespace Repository;

use Core\AccessRight\AccessRight;
use Core\Config;
use Core\EntityDataBuilder;
use Exception\CustomPdoException;
use Exception\ResponseException;
use Models\Book;
use Models\Entity;
use Models\Product;
use Models\ProductUserData;
use Models\Shop;
use Models\Stock;
use PDOException;
use QueryPdo;

class ProductRepository extends Repository
{
    protected string $entityModel = Product::class;

    private ProductUserDataRepository $productUserDataRepository;
    private BookRepository $bookRepository;
    private SourceProductRepository $sourceProductRepository;
    private StockRepository $stockRepository;
    private PriceDateRepository $priceDateRepository;
    private SameProductRepository $sameProductRepository;

    public function __construct()
    {
        parent::__construct();

        $this->productUserDataRepository = new ProductUserDataRepository();
        $this->bookRepository = new BookRepository();
        $this->sourceProductRepository = new SourceProductRepository();
        $this->stockRepository = new StockRepository();
        $this->priceDateRepository = new PriceDateRepository();
        $this->sameProductRepository = new SameProductRepository();
    }

    public function save(array $entityData): int
    {
        if (!isset($entityData[Product::PARAM_PRODUCT_ID])) {
            throw new ResponseException('product_id is required');
        }

        if (!isset($entityData[Product::PARAM_SHOP_TYPE])) {
            throw new ResponseException('shop_id is required');
        }

        $stocks = $entityData[Product::PARAM_STOCKS] ?? [];
        $priceDates = $entityData[Product::PARAM_PRICE_DATES] ?? [];
        $flags = $entityData[Product::PARAM_FLAGS] ?? [];

        $entityId = null;
        $positionPrice = null;
        $product = $this->getProduct($entityData[Product::PARAM_PRODUCT_ID], $entityData[Product::PARAM_SHOP_TYPE]);

        if ($product) {
            $entityId = $product->getId();
            $positionPrice = $product->getMinPrice();
        }

        $toChangeId = isset($flags[Product::FLAG_TO_CHANGE_ID]) && Config::isWildberriesShopType();

        /**
         * Если находит товар с не пустым code у wildberries, значит уже заменён product_id.
         * Сохранение не производим, передаваемые ид неверны.
         */
        if ($toChangeId && !$entityId) {
            $entityId = $this->tryToChangeId($entityData);

            if ($entityId) {
                return $entityId;
            }
        }

        if (isset($flags[Product::FLAG_TO_LINK_BOOK])) {
            if (!$entityId) {
                throw new \Exception('При привязки книги не найден товар.');
            }

            if (!isset($entityData[Product::PARAM_BOOK])) {
                throw new \Exception('Не найдена книга в товаре для линка.');
            }

            $this->bookRepository->linkBookToProduct($entityId, $entityData[Product::PARAM_BOOK][Entity::PARAM_ID]);

            return $entityId;
        }

        if (isset($flags[Product::FLAG_TO_UNLINK_BOOK])) {
            if (!$entityId) {
                throw new \Exception('При отвязки книги не найден товар.');
            }

            $this->bookRepository->unlinkBookFromProduct($entityId);

            return $entityId;
        }

        if (isset($flags[Product::FLAG_TO_LINK_SOURCE_PRODUCT])) {
            if (!$entityId) {
                throw new \Exception('При привязки источника товара не найден товар.');
            }

            if (!isset($entityData[Product::PARAM_SOURCE_PRODUCT])) {
                throw new \Exception('Не найден источник товара в товаре для линка.');
            }

            $this->sourceProductRepository->linkToProduct($entityId, $entityData[Product::PARAM_SOURCE_PRODUCT][Entity::PARAM_ID]);

            return $entityId;
        }

        if (isset($flags[Product::FLAG_TO_UNLINK_SOURCE_PRODUCT])) {
            if (!$entityId) {
                throw new \Exception('При отвязки источника товара не найден товар.');
            }

            $this->sourceProductRepository->unlinkFromProduct($entityId);

            return $entityId;
        }

        if (!$entityId) {
            $entityId = $this->create($entityData);
        } elseif (isset($flags[Product::FLAG_TO_SAVE_PRODUCT])) {
            $this->update($entityData);
        }

        if (isset($flags[Product::FLAG_TO_SAVE_PRODUCT_USER_DATA])
            && isset($entityData[Product::PARAM_PRODUCT_USER_DATA])
        ) {
            $entityData[Product::PARAM_PRODUCT_USER_DATA][ProductUserData::PARAM_PRODUCT_ID] = $entityId;

//            $isNew = $isNew && !isset($entityData[Product::PARAM_PRODUCT_USER_DATA][Product::PARAM_USER_ID]);

            $pud = $this->productUserDataRepository->get($entityId);

            if (!$pud) {
                $this->productUserDataRepository->create($entityData[Product::PARAM_PRODUCT_USER_DATA]);
            } else {
                $this->productUserDataRepository->update($entityData[Product::PARAM_PRODUCT_USER_DATA]);
            }
        }

        if (!$entityId) {
            throw new \Exception('Entity is not exists for save priceDates and stocks.');
        }

        // Если цена не уменьшилась, то приходит ошибочный запрос на добавление, сток тоже не обрабатываем.
        if (isset($flags[Product::FLAG_TO_SAVE_PRICE_DATES]) && $priceDates) {
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

        if (isset($flags[Product::FLAG_TO_SAVE_STOCKS]) && $stocks && !$isLastStockEqualsQty) {
            $this->stockRepository->saveStocks($entityId, $stocks);
        }

        return $entityId;
    }

    protected function update(array $entityData): int
    {
        if (!AccessRight::hasAccess('product.save')) {
            throw new \RuntimeException('Save product is not granted');
        }

        $entityDataBuilder = $this->getEntityDataBuilder($entityData);

        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                $entityDataBuilder->getQueryPreparedData()
            )
            ->where(Product::PARAM_PRODUCT_ID, ':product_id')
            ->where(Product::PARAM_SHOP_ID, ':shop_id')
//            ->where(Product::PARAM_USER_ID, ':user_id')
            ->bindParams([
                Product::PARAM_SHOP_ID => $entityDataBuilder->getPreparedData(Product::PARAM_SHOP_ID),
//                Product::PARAM_USER_ID => Config::getCurrentUserid(),
                Product::PARAM_PRODUCT_ID => $entityDataBuilder->getEntityData(Product::PARAM_PRODUCT_ID),
            ]);

        try {
            $query->execute();

//            if (!$stmt->rowCount()) {
//                throw  new \Exception('Обновлено ' . $stmt->rowCount() . ' позиций');
//            }

            return $entityDataBuilder->getEntityData(Product::PARAM_ID);
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductRepository.update', $query, $e);
        }
    }

    protected function create(array $entityData): int
    {
        if (!AccessRight::hasAccess('product.create')) {
            throw new \RuntimeException('Create product is not granted');
        }

        $entityDataBuilder = $this->getEntityDataBuilder($entityData);
        $entityDataBuilder->appendPreparedData([
//            Product::PARAM_USER_ID => Config::getCurrentUserid(),
            Product::PARAM_PRODUCT_ID => $entityDataBuilder->getEntityData(Product::PARAM_PRODUCT_ID),
        ]);

        $query = (new QueryPdo())
            ->insert(Product::TABLE_NAME, $entityDataBuilder->getQueryPreparedData());

        try {
            $query->execute();

            return $query->getLastInsertId();
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductRepository.create', $query, $e);
        }
    }

    public function changeProductIsArchive($productId, bool $isArchive): void
    {
        $subQuery = (new QueryPdo())
            ->select(Entity::PARAM_ID)
            ->from(Product::TABLE_NAME)
            ->where(Product::PARAM_PRODUCT_ID, ':product_id')
            ->where(Product::PARAM_SHOP_ID, ':shop_id')
            ->where(Product::PARAM_USER_ID, ':user_id')
            ->bindParams([
                Product::PARAM_PRODUCT_ID => $productId,
                Product::PARAM_SHOP_ID => Config::getCurrentShopId(),
                Product::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        $query = (new QueryPdo())
            ->update(
                ProductUserData::TABLE_NAME,
                [ProductUserData::PARAM_IS_ARCHIVE => $isArchive]
            )
            ->where(ProductUserData::PARAM_PRODUCT_ID, $subQuery->assemble())
            ->where(ProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductRepository.changeProductIsArchive', $query, $e);
        }
    }

    public function removeByProductId($productId): bool
    {
        $subQuery = (new QueryPdo())
            ->select(Entity::PARAM_ID)
            ->from(Product::TABLE_NAME)
            ->where(Product::PARAM_PRODUCT_ID, ':product_id')
            ->where(Product::PARAM_SHOP_ID, ':shop_id')
            ->where(Product::PARAM_USER_ID, ':user_id')
            ->bindParams([
                Product::PARAM_PRODUCT_ID => $productId,
                Product::PARAM_SHOP_ID => Config::getCurrentShopId(),
                Product::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        $query = (new QueryPdo())
            ->update(
                Product::TABLE_NAME,
                [ProductUserData::PARAM_IS_ARCHIVE => TRUE]
            )
            ->where(ProductUserData::PARAM_PRODUCT_ID, $subQuery->assemble())
            ->where(ProductUserData::PARAM_USER_ID, ':user_id')
            ->bindParams([
                ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
            ]);

        try {
            $query->execute();

            return true;
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductRepository.removeByProductId', $query, $e);
        }
    }

    /**
     * Получаем массив моделей продуктов по ID.
     *
     * @param array       $productIds Массив ID товаров.
     * @param string|null $shopType Тип магазина.
     * @param bool        $withCode Получать продукт с заполненным кодом.
     *
     * @return Product[] Массив моделей продуктов.
     *
     * @throws \Exception
     */
    public function getProductsByProductIds(
        array $productIds,
        string $shopType = null,
        bool $withCode = true,
        $withArchive = false
    ): array
    {
        $query = $this->getQuery($productIds, $shopType, true);

        $query->where(
            sprintf(
                '%s.%s',
                $query->getTablePrefix(ProductUserData::TABLE_NAME),
                ProductUserData::PARAM_IS_ARCHIVE
            ),
            $withArchive
        );

        if (Config::isWildberriesShopType()) {
            if ($withCode) {
                $query->where('code', QueryPdo::EXPR_IS_NOT_NULL);
            } else {
                $query->where('code', QueryPdo::EXPR_IS_NULL);
            }
        }

        return $this->assembleQueryToModels($query->fetchAll(), true);
    }

    /**
     * Получаем одну модель продукта со всеми зависимостями.
     *
     * @param int    $productId    ID продукта.
     * @param string $shopType     Тип магазина.
     * @param bool   $addRelations Добавить все зависимости.
     *
     * @return Product|null
     *
     * @throws \Exception
     */
    public function getProduct(int $productId, string $shopType = null, bool $addRelations = false): Product|null
    {
        $query = $this->getQuery($productId, $shopType);
        $row = $query->fetch();

        if (!$row) {
            return null;
        }

        if (!$addRelations) {
            return $this->assembleModel($row);
        }

        $models = $this->assembleQueryToModels([$row], true);

        return array_shift($models) ?? null;
    }

    /**
     * Сделать абстрактным или в интерфейс?
     *
     * @param int|array $productId
     * @param string|null $shopType
     *
     * @return QueryPdo
     *
     * @throws \Exception
     */
    public function getQuery(int|array $productId, string $shopType = null, bool $findByUser = false): QueryPdo
    {
        $query = $this->getListQueryNew();
        $query->where('shop_id', ':' . Product::PARAM_SHOP_ID);
        $query->bindParams([
            Product::PARAM_SHOP_ID => $shopType ? Config::getShopIdByType($shopType) : Config::getCurrentShopId(),
        ]);

        $foundProductId = $productId;

        if ($findByUser) {
//            $subQuery = (new QueryPdo())
//                ->select(ProductUserData::PARAM_PRODUCT_ID)
//                ->from(ProductUserData::TABLE_NAME)
//                ->where(ProductUserData::PARAM_PRODUCT_ID, $productId)
//                ->where(ProductUserData::PARAM_USER_ID, ':user_id')
//                ->bindParams([
//                    ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
//                ])
//                ->limit(1);
//
//            $foundProductId = $subQuery->assemble();
        }

        $query->where('product_id', $foundProductId);// Массив передаётся, пока ошибка будет, если через bindParams.

        if (is_array($productId)) {
            if (count($productId) === 1) {
                $query->limit(1);
            } else if (Config::getCurrentUserid() !== 2) {
                $query->limit(100);
            }
        }

        return $query;
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

        $query->where('book_id', ':book_id')
            ->where('shop_id', QueryPdo::EXPR_IS_NOT_NULL)
            ->bindParams([
                Product::PARAM_BOOK_ID => $bookId
            ]);

        $rows = $query->fetchAll();

        return $this->assembleQueryToModels($rows);
    }

    /**
     * Собираем данные в модели.
     *
     * @param array $rows Данные модели.
     * @param bool $addRelations Добавлять модели, имеющие связь один к многим.
     *
     * @return array
     */
    private function assembleQueryToModels(array $rows, bool $addRelations = false): array
    {
        $ids = array_column($rows, Entity::PARAM_ID);

        $priceDatesData = [];
        $stocksData = [];
        $sameProductDataRows = [];

        if ($addRelations && $ids) {
            $priceDatesData = $ids ? $this->priceDateRepository->getPriceDatesForProducts($ids) : [];
            $stocksData = $ids ? $this->stockRepository->getStocksForProducts($ids) : [];
            $sameProductDataRows = $ids ? $this->sameProductRepository->getAllSameProducts($ids) : [];
        }

        return array_map(function ($productData) use ($priceDatesData, $stocksData, $sameProductDataRows) {
            return $this->assembleModel(
                $productData,
                $priceDatesData,
                $stocksData,
                $sameProductDataRows
            );
        }, $rows);
    }

    private function assembleModel(
        array $productData,
        array $priceDatesData = [],
        array $stocksData = [],
        array $sameProductDataRows = []
    ): Product {
        $productId = $productData[Entity::PARAM_ID];

        $productBookId = $productData[Product::PARAM_BOOK_ID] ?? 0;
        $productSourceProductId = $productData[Product::PARAM_SOURCE_PRODUCT_ID] ?? 0;

        $productData[Product::PARAM_PRICE_DATES] = $priceDatesData[$productId] ?? [];
        $productData[Product::PARAM_STOCKS] = $stocksData[$productId] ?? [];

        $sameProducts = [];
        if ($productBookId) {
            $sameProducts = isset($sameProductDataRows['book-' . $productBookId])
                ? $this->sameProductRepository->prepareSameProducts(
                    $productData,
                    $sameProductDataRows['book-' . $productBookId]
                )
                : [];
        } else if ($productSourceProductId) {
            $sameProducts = isset($sameProductDataRows['source-product-' . $productSourceProductId])
                ? $this->sameProductRepository->prepareSameProducts(
                    $productData,
                    $sameProductDataRows['source-product-' . $productSourceProductId]
                )
                : [];
        }

        $productData[Product::PARAM_SAME_PRODUCTS] = $sameProducts;

        return new Product($productData);
    }

//    private function getListQueryVariables(array $newVariables = []): array
//    {
//        $variables = [
////            'shop_id' => Config::getCurrentShopId(),
//            Product::PARAM_USER_ID => Config::getCurrentUserid(),
//        ];
//
//        return array_merge($variables, $newVariables);
//    }

    protected function getEntityDataBuilder(array $data): EntityDataBuilder
    {
//        $data[Product::PARAM_USER_ID] = Config::getCurrentUserid();
        $data[Product::PARAM_SHOP] = [
            Shop::PARAM_ID => Config::getShopIdByType($data['shop_type']),
            Shop::PARAM_TYPE => $data['shop_type'],
        ];

        return parent::getEntityDataBuilder($data);
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
        try {
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
//                ->where(Product::PARAM_USER_ID, ':user_id')
                ->bindParams([
                    Product::PARAM_ID => $positionId,
                    Product::PARAM_SHOP_ID => Config::getCurrentShopId(),
//                    Product::PARAM_USER_ID => Config::getCurrentUserid(),
                ]);

            $query->execute();

            return $query->getRowCount();
        } catch(PDOException $e) {
            throw new CustomPdoException('ProductRepository.changeId', $query, $e);
        }
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
        $product = $this->getProduct($data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_SHOP_TYPE]);
        if (!$product) {
            return null;
        }

        $affectedCount = $this->changeId($product->getId(), $data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_CODE]);

        return $product->getId();
    }
}