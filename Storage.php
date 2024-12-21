<?php

use Core\AccessRight\AccessRight;
use Core\Config;
use Models\Book;
use Models\Entity;
use Models\Product;
use Models\SourceProduct;
use Models\ProductUserData;
use Repository\BookRepository;
use Repository\ProductRepository;
use Repository\SourceProductRepository;
use Repository\StockRepository;
use Repository\ProductUserDataRepository;

class Storage {
    private ProductUserDataRepository $productUserDataRepository;
    private ProductRepository $productRepository;
    private BookRepository $bookRepository;
    private SourceProductRepository $sourceProductRepository;
    private StockRepository $stockRepository;
    private tResponse $tResponse;

    public function __construct(tResponse $tResponse)
    {
        $this->productUserDataRepository = new ProductUserDataRepository();
        $this->productRepository = new ProductRepository();
        $this->bookRepository = new BookRepository();
        $this->sourceProductRepository = new SourceProductRepository();
        $this->stockRepository = new StockRepository();

        $this->tResponse = $tResponse;
    }

    public function preDispatch(string $actionMethod, array $data): void
    {

    }

    public function postDispatch(string $actionMethod, array $data): void
    {
        $this->tResponse->appendData([
            'config' => [
                'source_product_types' => Config::getSourceProductTypes(),
                'book_binding_types' => Config::getBookBindingTypes(),
            ],
            'access_rights' => AccessRight::getRights()
        ]);
    }

    // api call
    public function getBooksByTitle(array $data): void
    {
        if (!isset($data['title'])) {
            throw new \Exception('Not found title');
        }

        $result = $this->bookRepository->getBooksByTitle($data['title']);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'books' => array_map(function (Book $model) {
                return $model->toArray();
            }, $result)
        ]);
    }

    // api call
    public function getSourceProductsByTitle(array $data): void
    {
        if (!isset($data['title'])) {
            throw new \Exception('Not found title');
        }

        $result = $this->sourceProductRepository->getSourceProductsByTitle($data['title']);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'products' => array_map(function (SourceProduct $model) {
                return $model->toArray();
            }, $result)
        ]);
    }

    // api call
    public function changeProductIsArchive(array $data): void
    {
        if (empty($data[Product::PARAM_PRODUCT_ID])) {
            throw new \Exception('Not found product_id');
        }

        if (!isset($data[ProductUserData::PARAM_IS_ARCHIVE])) {
            throw new \Exception('Not found is_archive');
        }

        // Сначала получаем, потом сейвим или создаём новый?
        $product = $this->productRepository->getProduct($data[Product::PARAM_PRODUCT_ID], null, true);

        if ($product) {
            $isArchive = (bool)$data[ProductUserData::PARAM_IS_ARCHIVE];

            if (!$product->getProductUserData()) {
                $this->productUserDataRepository->create([
                    ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
                    ProductUserData::PARAM_PRODUCT_ID => $product->getId(),
                    ProductUserData::PARAM_AVAILABLE => true,
                    ProductUserData::PARAM_IS_ARCHIVE => $isArchive,
                ]);

                $pud = $this->productUserDataRepository->get($product->getId());

                $product->setProductUserData($pud);
            } else {
                $this->productRepository->changeProductIsArchive(
                    (string)$data['product_id'],
                    $isArchive
                );

                $product->getProductUserData()->setIsArchive($isArchive);
            }
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'product' => $product?->toArray()
        ]);
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
    public function getProductByShopType(array $data): void
    {
        if (empty($data[Entity::PARAM_ID])) {
            throw new \Exception('Не указан передаваемый ID товара.');
        }

        $product = $this->productRepository->getProduct($data['id'], null, true);
        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'product' => $product?->toArray()
        ]);
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
            'items' =>  array_map(function ($product) {return $product->toArray();}, $products)
        ]);
    }

    // api call
    public function saveBook(array $modelData): void
    {
        QueryPdo::beginTransaction();
        try {
            $entityId = $this->bookRepository->processSave($modelData);

            QueryPdo::commit();
        } catch(\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        /** @var Book $model */
        $model = $this->bookRepository->find($entityId);

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
        QueryPdo::beginTransaction();
        try {
            $entityId = $this->sourceProductRepository->processSave($modelData);

            QueryPdo::commit();
        } catch(\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        $model = $this->sourceProductRepository->find($entityId);

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
        QueryPdo::beginTransaction();
        try {
            $this->productRepository->save($productData);

            QueryPdo::commit();
        } catch(\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        $model = $this->productRepository->getProduct($productData[Product::PARAM_PRODUCT_ID], null, true);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setMessage('Product is saved');
        $this->tResponse->setData([
            'product' => $model->toArray()
        ]);
    }

    // api call
    public function importByShopType(array $productsData): void
    {
        throw new RuntimeException('ImportByShopType is not implemented');

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
                QueryPdo::beginTransaction();
                try {
                    $this->productRepository->save($productData);

                    QueryPdo::commit();
                } catch(\Throwable $e) {
                    QueryPdo::rollBack();

                    throw $e;
                }

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
