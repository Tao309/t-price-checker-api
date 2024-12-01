<?php

use Models\Book;
use Repository\ProductRepository;
use Repository\BookRepository;
use Repository\StockRepository;
use Repository\SourceProductRepository;
use Models\SourceProduct;
use Core\Config;
use Core\AccessRight;

class Storage {
    private ProductRepository $productRepository;
    private BookRepository $bookRepository;
    private SourceProductRepository $sourceProductRepository;
    private StockRepository $stockRepository;
    private tResponse $tResponse;

    public function __construct(tResponse $tResponse)
    {
        $this->productRepository = new ProductRepository();
        $this->bookRepository = new BookRepository();
        $this->sourceProductRepository = new SourceProductRepository();
        $this->stockRepository = new StockRepository();

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
            array_map(function (Book $model) {
                return $model->toArray();
            }, $result)
        );
    }

    // api call
    public function getSourceProductsByTitle(array $data): void
    {
        if (!isset($data['title'])) {
            throw new \Exception('Not found title');
        }

        $result = $this->sourceProductRepository->getSourceProductsByTitle($data['title']);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData(
            array_map(function (SourceProduct $model) {
                return $model->toArray();
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
        $this->tResponse->setData([
            'items' =>  array_map(function ($product) {return $product->toArray();}, $products),
            'config' => [
                'source_product_types' => Config::getSourceProductTypes(),
                'book_binding_types' => Config::getBookBindingTypes(),
            ],
            'access_right' => [
                'is_save_book_available' => AccessRight::isSaveBookAvailable(),
                'is_source_product_available' => AccessRight::isSaveSourceProductAvailable(),
                'is_limit_viewed' => AccessRight::isProductsViewedLimitAvailableForUser(),
                'limit_viewed' => AccessRight::getProductsViewedLimitForUser(),
                'is_create_book_available' => AccessRight::isCreateBookAvailableForUser(),
                'create_book_available_limit' => AccessRight::getCreateBookLimitForUser(),
                'is_create_source_product_available' => AccessRight::isCreateSourceProductAvailableForUser(),
                'create_source_product_available_limit' => AccessRight::getCreateSourceProductLimitForUser(),
            ]
        ]);
    }

    // api call
    public function saveBook(array $modelData): void
    {
        $entityId = $this->bookRepository->save($modelData);

        $model = $this->bookRepository->get($entityId);

        if (!$model) {
            throw new \Exception('Not found book by id '. $entityId);
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'entity' => $model->toArray()
        ]);
        $this->tResponse->setMessage('Book is saved');
    }

    // api call
    public function saveSourceProduct(array $modelData): void
    {
        $entityId = $this->sourceProductRepository->save($modelData);

        $model = $this->sourceProductRepository->get($entityId);

        if (!$model) {
            throw new \Exception('Not found sourceProduct by id '. $entityId);
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'entity' => $model->toArray()
        ]);
        $this->tResponse->setMessage('Book is saved');
    }

    // api call
    public function saveProduct(array $productData): void
    {
        $this->productRepository->save($productData);
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
                $this->productRepository->save($productData);
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
