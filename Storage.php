<?php

use Core\AccessRight\AccessRight;
use Core\Config;
use Models\Book;
use Models\Product;
use Models\SourceProduct;
use Models\ProductUserData;
use Repository\BookRepository;
use Repository\ProductRepository;
use Repository\SourceProductRepository;
use Repository\StockRepository;
use Repository\ProductUserDataRepository;
use Core\ArrayHandler;

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
            'access_rights' => AccessRight::getRights(),
            'app_version' => Config::APP_VERSION
        ]);
    }

    // api call
    public function getBooksByTitle(array $data): void
    {
        ArrayHandler::hasParamThroughException(Book::PARAM_TITLE, $data, 'Not found title');

        $result = $this->bookRepository->getBooksByTitle(ArrayHandler::getValueAsString(Book::PARAM_TITLE, $data));

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
        ArrayHandler::hasParamThroughException(SourceProduct::PARAM_TITLE, $data, 'Not found title');

        $result = $this->sourceProductRepository->getSourceProductsByTitle(
            ArrayHandler::getValueAsString(SourceProduct::PARAM_TITLE, $data)
        );

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
        ArrayHandler::hasParamThroughException(Product::PARAM_SHOP_PRODUCT_ID, $data, 'Not found shop_product_id');
        ArrayHandler::hasParamThroughException(ProductUserData::PARAM_IS_ARCHIVE, $data, 'Not found is_archive');

        $shopProductId = ArrayHandler::getValueAsString(Product::PARAM_SHOP_PRODUCT_ID, $data);
        $product = $this->productRepository->findProduct($shopProductId, true);

        if ($product) {
            $isArchive = ArrayHandler::getValueAsBool(ProductUserData::PARAM_IS_ARCHIVE, $data);

            if (!$product->getProductUserData()) {
                $this->productUserDataRepository->save([
                    ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
                    ProductUserData::PARAM_PRODUCT_ID => $product->getId(),
                    ProductUserData::PARAM_AVAILABLE => true,
                    ProductUserData::PARAM_IS_ARCHIVE => $isArchive,
                ]);

                $pud = $this->productUserDataRepository->find($product->getId());

                $product->setProductUserData($pud);
            } else {
                $this->productUserDataRepository->changeIsArchive($product->getId(), $isArchive);
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
    public function getProductsByShopType(array $data): void
    {
        ArrayHandler::hasParamThroughException('ids', $data, 'Не указан передаваемый массив ID товаров.');

        $productIds = json_decode(
            ArrayHandler::getValueAsString('ids', $data),
            true
        );

        if (!is_array($productIds)) {
            throw new \Exception('Не корректен передаваемый массив ID товаров.');
        }

        $products = $this->productRepository->getProductsByShopProductIds($productIds);

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
            $entityId = $this->bookRepository->save($modelData);

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
            $entityId = $this->sourceProductRepository->save($modelData);

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
            $this->productRepository->saveProduct($productData);

            QueryPdo::commit();
        } catch(\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        $model = $this->productRepository->findProduct(
            ArrayHandler::getValueAsString(Product::PARAM_SHOP_PRODUCT_ID, $productData)
        );

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
    }

    // api call
    public function saveProducts(array $data): void
    {
        ArrayHandler::hasParamThroughException('products', $data, 'Не указан передаваемый массив товаров для сохранения.');

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
                    $this->productRepository->saveProduct($productData);

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
