<?php

use Models\Book as Book;
use Models\Entity;
use Models\SameProduct;

class tPdo implements tPdoInterface {
    /** @var string Тип хранилища по маркетплейсу. */
    private string $type;

    private int $userId;

    private int $currentShopId;

    private array $shopTypes = [];

    public function __construct(string $type, $userId)
    {
        $this->type = $type;
        $this->userId = $userId;

        $this->appplyShopIds();
    }

    public function getStock(array $stockData)
    {
        $query = (new QueryPdo())
            ->select(['*'])
            ->from(['products_stocks'])
            ->where('id = :id')
            ->where('qty = :qty')
            ->where('date = :date');

        return $query->fetch([
            'id' => $stockData['id'],
            'qty' => $stockData['qty'],
            'date' => $stockData['date']
        ]);
    }

    public function removeStock(array $stockData): int
    {
        if (empty($stockData['id']) || empty($stockData['qty']) || empty($stockData['date'])) {
            throw new \Exception('Не все поля заполнены для удаления');
        }

        $query = (new QueryPdo())
            ->delete(
                'products_stocks',
                [
                    'id' => ':id',
                    'qty' => ':qty',
                    'date' => ':date'
                ]
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        $stmt->execute([
            'id' => $stockData['id'],
            'qty' => $stockData['qty'],
            'date' => $stockData['date']
        ]);

        if (!$stmt->rowCount()) {
            throw new \Exception('Stock is not removed. Not row affected.');
        }

        return $stmt->rowCount();
    }

    public function getBooks(string $title): array
    {
        $query = (new QueryPdo())
            ->select([
                'b.*'
            ])
            ->from(['b' => 'books'])
            ->where('LOWER(b.title) LIKE :title')
            ->limit(7);

        return $query->fetchAll([
            'title' => '%'.strtolower(trim($title)).'%'
        ]);
    }

    public function getBookData(int $id): array
    {
        $query = (new QueryPdo())
            ->select([
                'b.*'
            ])
            ->from(['b' => 'books'])
            ->leftJoin(
                ['bbt' => 'book_binding_type'],
                'bbt.id = b.binding_type',
                [
                    'bbt.id AS \''.Book::PARAM_BINDING_TYPE_ID.'\'',
                    'bbt.label AS \''.Book::PARAM_BINDING_TYPE_LABEL.'\''
                ]
            )
            ->where('b.id = :id');

        return $query->fetch([
            'id' => $id
        ]);
    }

    public function deleteByProductId($productId): bool
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
            'shop_id' => $this->getCurrentShopId(),
            'user_id' => $this->getCurrentUserId(),
        ]);

        return true;
    }

    public function getProducts(array $productIds): array
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
                'bbt.id = b.binding_type',
                [
                    'bbt.id AS \''.Book::PARAM_BINDING_TYPE_ID.'\'',
                    'bbt.label AS \''.Book::PARAM_BINDING_TYPE_LABEL.'\''
                ]
            )
            ->where('p.user_id = :user_id')
            ->where('p.shop_id = :shop_id')
//            ->where('is_archive IS FALSE')
            ->where('p.product_id IN ('.implode(",", $productIds).')')
        ;

        if ($this->userId !== 2) {
            $query->limit(100);
        }

        return $query->fetchAll([
            'shop_id' => $this->currentShopId,
            'user_id' => $this->userId,
        ]);
    }

    /**
     * Получаем данные цен для списка продуктов, уже сгруппированные по id.
     *
     * @param array $ids Массив ID товаров.
     *
     * @return array Массив цен.
     */
    public function getPriceDatesForProducts(array $ids = []): array
    {
        $query = (new QueryPdo())
            ->select('*')
            ->from('products_dates')
            ->where('id IN ('.implode(",", $ids).')')
            ->order('date');

        $result = [];

        foreach ($query->fetchAll() as $row) {
            if (!isset($result[$row[Entity::PARAM_ID]])) {
                $result[$row[Entity::PARAM_ID]] = [];
            }

            $result[$row[Entity::PARAM_ID]][] = $row;
        }

        return $result;
    }

    /**
     * Получаем данные стоков для списка продуктов, уже сгруппированные по id.
     *
     * @param array $ids Массив ID товаров.
     *
     * @return array Массив стоков.
     */
    public function getStocksForProducts(array $ids = []): array
    {
        $query = (new QueryPdo())
            ->select('*')
            ->from('products_stocks')
            ->where('id IN ('.implode(",", $ids).')')
            ->order('date');

        $result = [];

        foreach ($query->fetchAll() as $row) {
            if (!isset($result[$row[Entity::PARAM_ID]])) {
                $result[$row[Entity::PARAM_ID]] = [];
            }

            $result[$row[Entity::PARAM_ID]][] = $row;
        }

        return $result;
    }

    /**
     * Получение похожих товаров с других магазинов, относительно текущего, уже сгруппированные по book_id.
     *
     * @param array $ids Массив id товаров.
     *
     * @return array Массив похожих товаров.
     */
    public function getAllSameProductsByBook(array $ids): array
    {
        $priceDatesSubQuery = (new QueryPdo())
            ->select(['id', 'MIN(price) AS price'])
            ->from('products_dates')
            ->group('id');

        $bookSubQuery = (new QueryPdo())
            ->select(['DISTINCT book_id'])
            ->from('products')
            ->where('id IN ('.implode(",", $ids).')')
        ;

        $query = (new QueryPdo())
            ->select([
                'p.id',
                'p.product_id',
                'p.book_id',
                'p.available'
            ])
            ->from(['p' => 'products'])
            ->rightJoin(
                ['s' => 'shops'],
                'p.shop_id = s.id',
                's.type AS shop_type'
            )
            ->leftJoin(
                ['pd' => '('.$priceDatesSubQuery->assemble().')'],
                'pd.id = p.id',
                [
                    'pd.price'
                ]
            )
            ->where('p.shop_id != :shop_id')
            ->where('p.user_id = :user_id')
            ->where('p.book_id IN ('.$bookSubQuery->assemble().')')
            ->order('pd.price');

        $rows = $query->fetchAll([
            'shop_id' => $this->currentShopId,
            'user_id' => $this->userId,
        ]);

        $result = [];

        foreach ($rows as $row) {
            if (!isset($result[$row[SameProduct::PARAM_BOOK_ID]])) {
                $result[$row[SameProduct::PARAM_BOOK_ID]] = [];
            }

            $result[$row[SameProduct::PARAM_BOOK_ID]][] = $row;
        }

        return $result;
    }

    public function saveBook(array $data): int
    {
        if (isset($data[Entity::PARAM_ID])) {
            return $this->updateBook($data);
        }

        return $this->insertNewBook($data);
    }

    public function saveProduct(array $data): void
    {
        //die('Saving products is temporary unavailable.');
        //var_dump($data);exit;
        //return;

        $stocks = $data[Product::PARAM_STOCKS] ?? [];
        $dates = $data[Product::PARAM_PRICE_DATES] ?? [];
        $flags = $data['flags'] ?? [];

        $positionId = null;
        $positionPrice = null;
        $positionQty = null;
        $positionData = $this->getPositionData($data['product_id'], $this->getShopIdByType($data['shop_type']));

//        var_dump(end($dates)['price']);exit;

        if ($positionData) {
            $positionId = $positionData['id'];
            $positionPrice = $positionData['price'];
            $positionQty = $positionData['qty'];
        }

        if (isset($flags[Product::FLAG_TO_LINK_BOOK])) {
            if (!$positionId) {
                throw new \Exception('При привязки книги не найден товар.');
            }

            if (!isset($data[Product::PARAM_BOOK])) {
                throw new \Exception('Не найдена книга в товаре для линка.');
            }

            $this->linkBookToProduct($data);
            return;
        } elseif (isset($flags[Product::FLAG_TO_UNLINK_BOOK])) {
            if (!$positionId) {
                throw new \Exception('При отвязки книги не найден товар.');
            }

            $this->unlinkBookFromProduct($data);
            return;
        }

        if (!$positionId) {
            $positionId = $this->insertNewPosition($data);
        } elseif (isset($flags[Product::FLAG_TO_SAVE_PRODUCT])) {
            $this->updatePosition($data);
        }

        if (!$positionId) {
            return;
        }

        if (isset($flags[Product::FLAG_TO_SAVE_PRICE_DATES])) {
            if (!($positionPrice && end($dates)['price'] > $positionPrice)) {
                $this->savePriceDates($positionId, $dates);
            }
        }

        if (isset($flags[Product::FLAG_TO_SAVE_STOCKS])) {
            if (!($positionQty && end($stocks)['qty'] == $positionQty)) {
                $this->saveStocks($positionId, $stocks);
            }
        }
    }

    private function getPreparedBookData(array $bookData): array
    {
        $result = [];
        foreach(Book::RECORDABLE_PARAMS as $param) {
            $result[$param] = $bookData[$param] ?: null;
        }

        $result[Book::PARAM_RELEASE_DATE] = $bookData[Book::PARAM_RELEASE_DATE] ?: null;

        if (isset($bookData[Book::PARAM_BINDING_TYPE])) {
            $result[Book::PARAM_BINDING_TYPE] = $bookData[Book::PARAM_BINDING_TYPE]['id'];
        }

        return $result;
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
            $productData['title'] = 'Title not exists';
        }

        $result = [
            'product_id' => $productData['product_id'],
            'shop_id' => $this->getShopIdByType($productData['shop_type']),
            'user_id' => $this->getCurrentUserId(),
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

    private function updateBook(array $bookData): int
    {
        $bookId = $bookData[Entity::PARAM_ID];
        $data = $this->getPreparedBookData($bookData);

        $query = (new QueryPdo())
            ->update(
                'books',
                $data,
                'id = :book_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            $variables = [
                'book_id' => $bookId
            ];

            $stmt->execute($variables);

            return $bookId;
        } catch(PDOException $e) {
            echo "\nupdateBook:\n";
            if ($e->getMessage()) {
                echo $e->getMessage() . "\n";
            }

            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r($data);
            echo "Variables:\n";
            print_r($variables);
            exit;
        }
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

        try {
            $variables = [
                'shop_id' => $shopId,
                'user_id' => $this->getCurrentUserId(),
                'product_id' => $productData['product_id'],
            ];

            $stmt->execute($variables);

//            if (!$stmt->rowCount()) {
//                throw  new \Exception('Обновлено ' . $stmt->rowCount() . ' позиций');
//            }
        } catch(PDOException $e) {
            echo "\nupdatePosition:\n";
            if ($e->getMessage()) {
                echo $e->getMessage() . "\n";
            }

            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r($data);
            echo "Variables:\n";
            print_r($variables);
            exit;
        }
    }

    private function linkBookToProduct(array $productData): void
    {
        $data = $this->getPreparedProductData($productData);
        $shopId = $data['shop_id'];

        $query = (new QueryPdo())
            ->update(
                'products',
                [
                    'book_id' => $data[Product::PARAM_BOOK][Entity::PARAM_ID]
                ],
                'product_id = :product_id AND shop_id = :shop_id AND user_id = :user_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            $variables = [
                'shop_id' => $shopId,
                'user_id' => $this->getCurrentUserId(),
                'product_id' => $productData['product_id'],
            ];

            $stmt->execute($variables);
        } catch(PDOException $e) {
            echo "\nupdatePosition:\n";
            if ($e->getMessage()) {
                echo $e->getMessage() . "\n";
            }

            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r($data);
            echo "Variables:\n";
            print_r($variables);
            exit;
        }
    }

    private function unlinkBookFromProduct(array $productData): void
    {
        $data = $this->getPreparedProductData($productData);
        $shopId = $data['shop_id'];

        $query = (new QueryPdo())
            ->update(
                'products',
                [
                    'book_id' => null
                ],
                'product_id = :product_id AND shop_id = :shop_id AND user_id = :user_id'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            $variables = [
                'shop_id' => $shopId,
                'user_id' => $this->getCurrentUserId(),
                'product_id' => $productData['product_id'],
            ];

            $stmt->execute($variables);
        } catch(PDOException $e) {
            echo "\nupdatePosition:\n";
            if ($e->getMessage()) {
                echo $e->getMessage() . "\n";
            }

            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r($data);
            echo "Variables:\n";
            print_r($variables);
            exit;
        }

    }

    private function insertNewBook(array $bookData): int
    {
        $arrayValues = [];
        foreach(Book::RECORDABLE_PARAMS as $param) {
            $arrayValues[$param] = ':' . $param;
        }

        $arrayValues[Book::PARAM_RELEASE_DATE] = ':' . Book::PARAM_RELEASE_DATE;

        if (isset($bookData[Book::PARAM_BINDING_TYPE])) {
            $arrayValues[Book::PARAM_BINDING_TYPE] = ':binding_type';
        }

        $query = (new QueryPdo())
            ->insert('books', $arrayValues);

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            $stmt->execute($this->getPreparedBookData($bookData));

            return $dbh->lastInsertId();
        } catch(PDOException $e) {
            echo "\ninsertNewBook:\n";
            if ($e->getMessage()) {
                echo $e->getMessage() . "\n";
            }
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r($this->getPreparedBookData($bookData));
            exit;
        }
    }

    private function insertNewPosition(array $productData): int
    {
        $query = (new QueryPdo())
            ->insert('products', [
                'product_id' => ':product_id',
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
            echo "\ninsertNewPosition:\n";
            if ($e->getMessage()) {
                echo $e->getMessage() . "\n";
            }
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r($data);
            exit;
        }
    }

    private function savePriceDates(int $positionId, array $priceDates = []): void
    {
        if (empty($priceDates)) {
            return;
        }

        $query = (new QueryPdo())
            ->insert(
                'products_dates',
                [
                    'id' => ':position_id',
                    'price' => ':price',
                    'date' => ':date',
                ],
                'price = VALUES(price)'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            foreach($priceDates as $priceDate) {
                $stmt->execute([
                    'position_id' => $positionId,
                    'price' => $priceDate['price'],
                    'date' => $priceDate['date']
                ]);
            }
        } catch(\Throwable $e) {
            echo "\nsavePriceDates:\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r(['position_id' => $positionId]);
            print_r($priceDates);
            exit;
        }
    }

    private function saveStocks(int $positionId, array $stocks = []): void
    {
        if (empty($stocks)) {
            return;
        }

        $query = (new QueryPdo())
            ->insert(
                'products_stocks',
                [
                    'id' => ':position_id',
                    'qty' => ':qty',
                    'date' => ':date',
                    'log' => ':log',
                ],
                'qty = VALUES(qty), log = VALUES(log)'
            );

        $dbh = QueryPdo::getConnect();
        $stmt = $dbh->prepare($query);

        try {
            foreach($stocks as $stock) {
                $stmt->execute([
                    'position_id' => $positionId,
                    'qty' => $stock['qty'],
                    'date' => $stock['date'],
                    'log' => isset($stock['log']) ? json_encode($stock['log']) : null
                ]);
            }
        } catch(\Throwable $e) {
            echo "\nsaveStocks:\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            print_r(['position_id' => $positionId]);
            print_r($stocks);
            exit;
        }
    }

    private function appplyShopIds()
    {
        $query = (new QueryPdo())->select('*')->from('shops');

        foreach ($query->fetchAll() as $row) {
            $this->shopTypes[$row['type']] = $row['id'];
        }

        $this->currentShopId = $this->getShopIdByType($this->type);
    }

    private function getShopIdByType(string $shopType)
    {
        if (!isset($this->shopTypes[$shopType])) {
            throw new \Exception('Not found id for shop type ' . $shopType);
        }

        return $this->shopTypes[$shopType];
    }

    private function getPositionData($productId, int $shopId)
    {
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

        return $query->fetch([
            'shop_id' => $shopId,
            'user_id' => $this->userId,
            'product_id' => $productId,
        ]);
    }

    private function getCurrentShopId(): int
    {
        return $this->currentShopId;
    }

    private function getCurrentUserId(): int
    {
        return $this->userId;
    }
}
