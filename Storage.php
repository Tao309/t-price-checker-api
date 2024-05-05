<?php

use Models\Book;
use Models\Entity;
use Repository\ProductRepository;
use Repository\BookRepository;
use Repository\StockRepository;
use Repository\PriceDateRepository;
use Repository\SameProductRepository;

class Storage {
    private ProductRepository $productRepository;
    private BookRepository $bookRepository;
    private StockRepository $stockRepository;
    private PriceDateRepository $priceDateRepository;
    private SameProductRepository $sameProductRepository;
    private tResponse $tResponse;

    public function __construct(tResponse $tResponse)
    {
        $this->productRepository = new ProductRepository();
        $this->bookRepository = new BookRepository();
        $this->stockRepository = new StockRepository();
        $this->priceDateRepository = new PriceDateRepository();
        $this->sameProductRepository = new SameProductRepository();

        $this->tResponse = $tResponse;
    }

    // api call
    public function getBooksByTitle(array $data): void
    {
        if (!isset($data['title'])) {
            throw new \Exception('Not found title');
        }

        $result = $this->bookRepository->getBooksByTitle($data['title']);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData(
            array_map(function (Book $book) {
                return $book->toArray();
            }, $result)
        );
    }

    // api call
    public function deleteProduct(array $data): void
    {
        if (empty($data['product_id'])) {
            throw new \Exception('Not found product_id');
        }

        $this->productRepository->removeByProductId($data['product_id']);
        $this->tResponse->setSuccess(true);
        $this->tResponse->setMessage('Product is removed');
    }

    // api call
    public function removeStock(array $stockData): void
    {
        $foundStock = $this->stockRepository->getStock($stockData);

        if (!$foundStock) {
            throw new \Exception('Stock is not found');
        }

        $countRemoved = $this->stockRepository->deleteStock($stockData);
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

        $products = $this->productRepository->getProductsByProductIds($productIds);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData(array_map(function ($product) {
            return $product->toArray();
        }, $products));
    }

    // api call
    public function saveBook(array $bookData): void
    {
        $entityId = $this->bookRepository->saveBook($bookData);

        $book = $this->bookRepository->getBook($entityId);

        if (!$book) {
            throw new \Exception('Not found book by id '. $entityId);
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'entity' => $book->toArray()
        ]);
        $this->tResponse->setMessage('Book is saved');
    }

    // api call
    public function saveProduct(array $productData): void
    {
        $this->productRepository->saveProduct($productData);
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

        $productsCount = 0;
        $errorsCount = 0;
        $savedCount = 0;
        $message = [];

        foreach ($productsData as $productData) {
            $productsCount++;

            try {
                $this->productRepository->saveProduct($productData);
                $savedCount++;
            } catch (\Throwable $e) {
                $errorsCount++;
                $message[] = $e->getMessage();
            }
        }

        $this->tResponse->setData([
            'products_count' => $productsCount,
            'errors_count' => $errorsCount,
            'saved_count' => $savedCount
        ]);

        if ($message) {
            $this->tResponse->setMessage(implode('. ', array_unique($message)));
        } else {
            $this->tResponse->setMessage('Products are saved');
            $this->tResponse->setSuccess(true);
        }
    }

}
