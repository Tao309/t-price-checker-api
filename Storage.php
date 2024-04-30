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
    private tResponse $tResponse;

    public function __construct(string $type, tResponse $tResponse)
    {
        $this->checkShopTypeAndApply($type);

        $this->tPdo = new tPdo($type, $this->userId);
        $this->tResponse = $tResponse;
    }

    // api call
    public function getBooksByTitle(array $data): void
    {
        if (!isset($data['title'])) {
            throw new \Exception('Not found title');
        }

        $result = [];

        $rows = $this->tPdo->getBooks($data['title']);

        foreach ($rows as $row) {
            $result[] = (new Book($row))->toArray();
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData($result);
    }

    // api call
    public function deleteProduct(array $data): void
    {
        if (empty($data['product_id'])) {
            throw new \Exception('Not found product_id');
        }

        $this->tPdo->deleteByProductId($data['product_id']);
        $this->tResponse->setSuccess(true);
        $this->tResponse->setMessage('Product is removed');
    }

    // api call
    public function removeStock(array $stockData): void
    {
        $foundStock = $this->tPdo->getStock($stockData);

        if (!$foundStock) {
            throw new \Exception('Stock is not found');
        }

        $countRemoved = $this->tPdo->removeStock($stockData);
        $this->tResponse->setSuccess(true);
        $this->tResponse->setMessage('Stock is removed, affected: '. $countRemoved);
    }

    // api call
    public function getProductsByShopType(array $data): void
    {
        if (empty($data['ids'])) {
            throw new \Exception('Не указан передаваемый массив ID товаров.');
        }

        $productIds = json_decode($data['ids'], true);

        if (!is_array($productIds)) {
            throw new \Exception('Не корректен передаваемый массив ID товаров.');
        }

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

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData($result);
    }

    // api call
    public function saveBook(array $bookData): void
    {
        $entityId = $this->tPdo->saveBook($bookData);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'entity' => (new Book($this->tPdo->getBookData($entityId)))->toArray()
        ]);
        $this->tResponse->setMessage('Book is saved');
    }

    // api call
    public function saveProduct(array $productData): void
    {
        $this->tPdo->saveProduct($productData);
        $this->tResponse->setSuccess(true);
        $this->tResponse->setMessage('Product is saved');
    }

    // api call
    public function importByShopType(array $productsData): void
    {
        $this->saveProducts($productsData);
    }

    // api call
    public function saveProducts(array $data): void
    {
        if (empty($data['products'])) {
            throw new \Exception('Не указан передаваемый массив товаров для сохранения.');
        }

        $productsData = json_decode($data['products'], true);

        if (!is_array($productsData)) {
            throw new \Exception('Не корректен передаваемый массив товаров для сохранения.');
        }

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

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'count' => $count,
            'error' => $error,
            'error_message' => implode(', ', $errorMessage),
        ]);
        $this->tResponse->setMessage('Products are saved');
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
