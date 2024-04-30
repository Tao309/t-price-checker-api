<?php

use Models\Book;
use Models\Entity;

class Storage {
    const TYPE_OZON = 'ozon';//www.ozon.ru
    const TYPE_WILDBERRIES = 'wildberries';//www.wildberries.ru
    const TYPE_CHITAI_GOROD = 'chitai-gorod';//www.chitai-gorod.ru
    const TYPE_FFAN = 'ffan';//ffan.ru
    const TYPE_KNIGOFAN = 'knigofan';//knigofan.ru

    const AVAILABLES_TYPES = [
        self::TYPE_OZON,
        self::TYPE_WILDBERRIES,
        self::TYPE_CHITAI_GOROD,
        self::TYPE_FFAN,
        self::TYPE_KNIGOFAN,
    ];

    /** @var string Тип хранилища по маркетплейсу. */
    private string $currentShopType;

    private int $userId = 2;// tao309.

    private tPdo $tPdo;

    public function __construct(string $type)
    {
        $this->checkShopTypeAndApply($type);

        $this->tPdo = new tPdo($type, $this->userId);
    }

    public function getBooksByTitle(string $title)
    {
        $result = [];

        $rows = $this->tPdo->getBooks($title);

        foreach ($rows as $row) {
            $result[] = (new Book($row))->toArray();
        }

        return $result;
    }


    public function deleteProduct($productId)
    {
        if (empty($productId)) {
            throw new \Exception('Product Id require.');
        }

        return $this->tPdo->deleteByProductId($productId);
    }

    public function removeStock(array $stockData): array
    {
        $success = false;
        $message = null;

        $foundStock = $this->tPdo->getStock($stockData);

        if ($foundStock) {
            try {
                if ($countRemoved = $this->tPdo->removeStock($stockData)) {
                    $message = 'Stock is removed: '. $countRemoved;
                    $success = true;
                } else {
                    $message = 'Stock is not removed. Not row affected.';
                }

            } catch (\Throwable $e) {
                $message = $e->getMessage();
            }
        } else {
            $message = 'Stock is not found';
        }

        return [
            'success' => $success,
            'message' => $message
        ];
    }

    /**
     * Получаем список товаров по списку ID.
     *
     * @param array $productIds Массив product_id товаров.
     *
     * @return Product[]
     */
    public function getProductsByShopType(array $productIds)
    {
        $productsData = $this->tPdo->getProducts($productIds);
        $ids = array_column($productsData, Entity::PARAM_ID);

        $priceDatesData = $this->tPdo->getPriceDatesForProducts($ids);
        $stocksData = $this->tPdo->getStocksForProducts($ids);
        $sameProductData = $this->tPdo->getAllSameProductsByBook($ids);

        $result = [];
        foreach ($productsData as $productData) {
            $productBookId = $productData[Product::PARAM_BOOK_ID] ?? 0;

            $productData[Product::PARAM_PRICE_DATES] = $priceDatesData[$productData[Entity::PARAM_ID]] ?: [];
            $productData[Product::PARAM_STOCKS] = $stocksData[$productData[Entity::PARAM_ID]] ?: [];
            $productData[Product::PARAM_SAME_PRODUCTS] = ($productBookId && isset($sameProductData[$productBookId]))
                ? $sameProductData[$productBookId]
                : [];

            $result[] = (new Product($productData))->toArray();
        }

        return $result;
    }

    public function saveBook(array $bookData): array
    {
        $success = false;
        $message = null;
        $entityId = null;

        try {
            $entityId = $this->tPdo->saveBook($bookData);

            $success = true;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        return [
            'success' => $success,
            'message' => $message,
            'entity' => (new Book($this->tPdo->getBookData($entityId)))->toArray()
        ];
    }

    public function saveProduct(array $productData): array
    {
        $success = false;
        $message = null;

        try {
            $this->tPdo->saveProduct($productData);

            $success = true;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
        }

        return [
            'success' => $success,
            'message' => $message
        ];
    }

    public function saveProducts(array $productsData): array
    {
        $count = 0;
        $error = 0;
        $errorMessage = [];

        foreach ($productsData as $productData) {
            $count++;

            try {
                $this->tPdo->saveProduct($productData);
            } catch (\Throwable $e) {
                $error++;
                $errorMessage[] = $e->getMessage();
                break;
            }
        }

        return [
            'count' => $count,
            'error' => $error,
            'error_message' => implode(', ', $errorMessage),
        ];
    }

    /**
     * Проверка корректности типа и применение его в свойства класса.
     *
     * @param string $type Тип хранилища.
     * @return void
     * @throws Exception
     */
    private function checkShopTypeAndApply(string $type)
    {
        if (!in_array($type, self::AVAILABLES_TYPES)) {
            throw new \Exception('Type ' . $type . ' is not available for storage.');
        }

        $this->currentShopType = $type;
    }

}
