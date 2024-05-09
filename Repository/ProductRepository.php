<?php

namespace Repository;

use Core\Config;
use Models\Book;
use Models\Entity;
use Models\Product;
use PDOException;
use QueryPdo;

class ProductRepository
{
    private BookRepository $bookRepository;
    private StockRepository $stockRepository;
    private PriceDateRepository $priceDateRepository;
    private SameProductRepository $sameProductRepository;

    public function __construct()
    {
        $this->bookRepository = new BookRepository();
        $this->stockRepository = new StockRepository();
        $this->priceDateRepository = new PriceDateRepository();
        $this->sameProductRepository = new SameProductRepository();
    }

    public function saveProduct(array $data): void
    {
        //die('Saving products is temporary unavailable.');
        //var_dump($data);exit;
        return;

        $stocks = $data[Product::PARAM_STOCKS] ?? [];
        $dates = $data[Product::PARAM_PRICE_DATES] ?? [];
        $flags = $data[Product::PARAM_FLAGS] ?? [];

        $positionId = null;
        $positionPrice = null;
        $positionQty = null;
        $product = $this->getProduct($data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_SHOP_TYPE]);

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
            $positionId = $this->createPosition($data);
        } elseif (isset($flags[Product::FLAG_TO_SAVE_PRODUCT])) {
            $this->updatePosition($data);
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
                'products',
                ['is_archive' => TRUE],
                'product_id = :product_id AND shop_id = :shop_id AND user_id = :user_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $stmt->execute([
            'product_id' => $productId,
            'shop_id' => Config::getCurrentShopId(),
            'user_id' => Config::getCurrentUserid(),
        ]);

        return true;
    }

    /**
     * @param array $productIds
     * @return Product[]
     */
    public function getProductsByProductIds(array $productIds): array
    {
        $query = $this->getListQuery();

        if (Config::getCurrentUserid() !== 2) {
            $query->limit(100);
        }

        $query->where('p.product_id IN ('.implode(",", $productIds).')');
        $query->where('p.shop_id = :shop_id');

        if (Config::isWildberriesShopType()) {
            $query->where('p.code IS NOT NULL');
        }

        if (Config::getCurrentUserid() !== 2) {
            $query->limit(100);
        }

        return $this->assembleQueryToModels(
            $query->fetchAll($this->getListQueryVariables(['shop_id' => Config::getCurrentShopId()]))
        );
    }

    /**
     * @param int $bookId
     * @return Product[]
     */
    public function getProductsByBookId(int $bookId): array
    {
        $query = $this->getListQuery();

        if (Config::isWildberriesShopType()) {
            $query->where('p.code IS NOT NULL');
        }

        $query->where('p.book_id = :book_id');
        $query->where('p.shop_id IS NOT NULL');

        $vars = $this->getListQueryVariables(['book_id' => $bookId]);

        $rows = $query->fetchAll($vars);

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

    private function getListQuery(): QueryPdo
    {
        $query = (new QueryPdo())
            ->select([
                'p.*'
            ])
            ->from(['p' => 'products'])
            ->rightJoin(
                ['s' => 'shops'],
                'p.shop_id = s.id',
                's.type AS shop_type'
            )
            ->leftJoin(
                ['b' => 'books'],
                'p.book_id = b.id',
                [
                    'b.id AS \'book.id\'',
                    'b.title AS \'book.title\'',
                    'b.author AS \'book.author\'',
                    'b.isbn AS \'book.isbn\'',
                    'b.pages AS \'book.pages\'',
                    'b.circulation AS \'book.circulation\'',
                    'b.size AS \'book.size\'',
                    'b.release_date AS \'book.release_date\'',
                    'b.publish_year AS \'book.publish_year\'',
                    'b.date_created AS \'book.date_created\''
                ]
            )
            ->leftJoin(
                ['bbt' => 'book_binding_type'],
                'bbt.id = b.binding_type_id',
                [
                    'bbt.id AS \'book.binding_type.id\'',
                    'bbt.label AS \'book.binding_type.label\''
                ]
            )
            ->where('p.user_id = :user_id')
//            ->where('is_archive IS FALSE')
        ;

        return $query;
    }

    private function getListQueryVariables(array $newVariables = []): array
    {
        $variables = [
//            'shop_id' => Config::getCurrentShopId(),
            'user_id' => Config::getCurrentUserid(),
        ];

        return array_merge($variables, $newVariables);
    }

    private function getPreparedProductData(array $productData): array
    {
        if (empty($productData['not_available_date_from']) || $productData['not_available_date_from'] === '1970-01-01T00:00:00.000Z') {
            $productData['not_available_date_from'] = null;
        }

        if (empty($productData['available_date_from']) || $productData['available_date_from'] === '1970-01-01T00:00:00.000Z') {
            $productData['available_date_from'] = null;
        }

        if(!isset($productData['title'])) {
            $productData['title'] = 'Empty Title';
        }

        $result = [
            'product_id' => $productData['product_id'],
            'code' => $productData['code'] ?? null,
            'shop_id' => Config::getShopIdByType($productData['shop_type']),
            'user_id' => Config::getCurrentUserid(),
            'title' => QueryPdo::escapeString($productData['title']),
            'available' => (bool)$productData['available'],
            'not_available_date_from' => $productData['not_available_date_from'] ?? null,
            'available_date_from' => $productData['available_date_from'] ?? null,
            'listen_price_value' => $productData['listen_price_value'] ?? null,
            'listen_qty_value' => $productData['listen_qty_value'] ?? null,
            'release_date' => $productData['release_date'] ?? null,
            'date_created' => $productData['date_created'] ?? date('Y-m-d H:i:s'),
            'date_updated' => $productData['date_updated'] ?? date('Y-m-d H:i:s')
        ];

        if (isset($productData[Product::PARAM_BOOK])) {
            $result[Product::PARAM_BOOK] = $productData[Product::PARAM_BOOK];
        }

        return $result;
    }

    private function updatePosition(array $productData): void
    {
        $data = $this->getPreparedProductData($productData);
        $shopId = $data['shop_id'];

        unset(
            $data['product_id'],
            $data['shop_id'],
            $data['user_id'],
            $data['date_created'],
            $data['date_updated'],
            $data['book'],
        );

        $query = (new QueryPdo())
            ->update(
                'products',
                $data,
                'product_id = :product_id AND shop_id = :shop_id AND user_id = :user_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $variables = [
            'shop_id' => $shopId,
            'user_id' => Config::getCurrentUserid(),
            'product_id' => $productData['product_id'],
        ];

        try {
            $stmt->execute($variables);

//            if (!$stmt->rowCount()) {
//                throw  new \Exception('Обновлено ' . $stmt->rowCount() . ' позиций');
//            }
        } catch(PDOException $e) {
            processPdoException('updatePosition', $variables, $data, $stmt, $e);
        }
    }

    private function createPosition(array $productData): int|null
    {
        $query = (new QueryPdo())
            ->insert('products', [
                'product_id' => ':product_id',
                'code' => ':code',
                'shop_id' => ':shop_id',
                'user_id' => ':user_id',
                'title' => ':title',
                'available' => ':available',
                'not_available_date_from' => ':not_available_date_from',
                'available_date_from' => ':available_date_from',
                'listen_price_value' => ':listen_price_value',
                'listen_qty_value' => ':listen_qty_value',
                'release_date' => ':release_date',
                'date_created' => ':date_created',
                'date_updated' => ':date_updated',
            ]);

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $data = $this->getPreparedProductData($productData);
        unset($data['book']);

        try {
            $stmt->execute($data);

            return $dbh->lastInsertId();
        } catch(PDOException $e) {
            processPdoException('createPosition', $variables, $data, $stmt, $e);

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
                'products',
                [
                    'product_id' => $productId,
                    'code' => $code
                ],
                'code is NULL AND id = :id AND shop_id = :shop_id AND user_id = :user_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $stmt->execute([
            'id' => $positionId,
            'shop_id' => Config::getCurrentShopId(),
            'user_id' => Config::getCurrentUserid(),
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
        $product = $this->getProduct($data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_SHOP_TYPE], false);
        if (!$product) {
            return null;
        }

        $affectedCount = $this->changeId($product->getId(), $data[Product::PARAM_PRODUCT_ID], $data[Product::PARAM_CODE]);

        return $product->getId();
    }

    public function getProduct($productId, string $shopType, $withCode = true): Product|null
    {
        $shopId = Config::getShopIdByType($shopType);

        $query = $this->getListQuery();

        $priceDatesSubQuery = (new QueryPdo())
            ->select(['id', 'MIN(price) AS price'])
            ->from('products_dates')
            ->group('id');

        $stockSubQuery = (new QueryPdo())
            ->select(['id', 'qty'])
            ->from('products_stocks')
            ->order('id', 'DESC')
            ->group('id');

        $query->leftJoin(
            ['pd' => '('.$priceDatesSubQuery->assemble().')'],
            'pd.id = p.id',
            [
                'pd.price AS min_price'
            ]
        )
        ->leftJoin(
            ['ps' => '('.$stockSubQuery->assemble().')'],
            'ps.id = p.id',
            [
                'ps.qty AS last_qty'
            ]
        );

        $query->where('p.shop_id = :shop_id');
        $query->where('p.product_id = :product_id');
        $query->limit(1);

        if (Config::isWildberriesShopType()) {
            if ($withCode) {
                $query->where('p.code IS NOT NULL');
            } else {
                $query->where('p.code IS NULL');
            }
        }

        $data = $query->fetch(
            $this->getListQueryVariables(
                [
                    'product_id' => $productId,
                    'shop_id' => $shopId,
                ]
            )
        );

        if (!$data) {
            return null;
//            throw new \Exception('Not found product data by id '. $productId . ' and shop_id ' . $shopId);
        }

        return new Product($data);
    }

    private function getPositionData($productId, string $shopType, $withCode = true)
    {
        $shopId = Config::getShopIdByType($shopType);

        $priceDatesSubQuery = (new QueryPdo())
            ->select(['id', 'MIN(price) AS price'])
            ->from('products_dates')
            ->group('id');

        $stockSubQuery = (new QueryPdo())
            ->select(['id', 'qty'])
            ->from('products_stocks')
            ->order('id', 'DESC')
            ->group('id');

        $query = (new QueryPdo())
            ->select('p.id')
            ->from(['p' => 'products'])
            ->leftJoin(
                ['pd' => '('.$priceDatesSubQuery->assemble().')'],
                'pd.id = p.id',
                [
                    'pd.price'
                ]
            )
            ->leftJoin(
                ['ps' => '('.$stockSubQuery->assemble().')'],
                'ps.id = p.id',
                [
                    'ps.qty'
                ]
            )
            ->where('p.shop_id = :shop_id')
            ->where('p.user_id = :user_id')
            ->where('p.product_id = :product_id');

        if (Config::isWildberriesShopType()) {
            if ($withCode) {
                $query->where('p.code IS NOT NULL');
            } else {
                $query->where('p.code IS NULL');
            }
        }

        return $query->fetch([
            'shop_id' => $shopId,
            'user_id' => Config::getCurrentUserid(),
            'product_id' => $productId,
        ]);
    }
}