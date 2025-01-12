<?php

namespace Core;

use AccessRights\AccessHandler;
use Models\Book;
use Models\BookUserData;
use Models\Product;
use Models\ProductUserData;
use Models\SourceProduct;
use Models\SourceProductUserData;
use Query\QueryPdo;
use Repository\BookRepository;
use Repository\BookUserDataRepository;
use Repository\ProductRepository;
use Repository\ProductUserDataRepository;
use Repository\SourceProductRepository;
use Repository\SourceProductUserDataRepository;
use Repository\StockRepository;

class ApiCaller
{
    private tResponse $tResponse;

    public function __construct(tResponse $tResponse)
    {
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
            'access_rights' => AccessHandler::getRights(),
            'app_version' => getenv('APP_VERSION') ?? 'not-found-version'
        ]);
    }

    // api call
    public function linkSourceProduct(array $data): void
    {
        ArrayHandler::hasParamThroughException('product_id', $data);
        ArrayHandler::hasParamThroughException('source_product_id', $data);

        $productId = ArrayHandler::getValueAsInt('product_id', $data);
        $sourceProductId = ArrayHandler::getValueAsInt('source_product_id', $data);
        $productRepository = new ProductRepository();
        $product = $productRepository->find($productId);

        if (!$product) {
            throw new \RuntimeException('Product is not found for link sourceProduct');
        }

        $sourceProductRepository = new SourceProductRepository();
        $sourceProductUserDataRepository = new SourceProductUserDataRepository();

        $spud = $sourceProductUserDataRepository->find(Config::getCurrentUserId(), $sourceProductId);

        if (!$spud) {
            $sourceProductUserDataRepository->create([
                SourceProductUserData::PARAM_USER_ID => Config::getCurrentUserId(),
                SourceProductUserData::PARAM_SOURCE_PRODUCT => $sourceProductId,
            ]);
        }

        $sourceProductRepository->linkToProduct($productId, $sourceProductId);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'product' => $productRepository->findProduct($product->getShopProductId(), true)->toArray(),
        ]);
    }

    // api call
    public function unlinkSourceProduct(array $data): void
    {
        ArrayHandler::hasParamThroughException('product_id', $data);
        ArrayHandler::hasParamThroughException('source_product_id', $data);

        $productId = ArrayHandler::getValueAsInt('product_id', $data);
        $sourceProductId = ArrayHandler::getValueAsInt('source_product_id', $data);
        $productRepository = new ProductRepository();
        $product = $productRepository->find($productId);

        if (!$product) {
            throw new \RuntimeException('Product is not found for unlink sourceProduct');
        }

        $sourceProductRepository = new SourceProductRepository();
        $sourceProductUserDataRepository = new SourceProductUserDataRepository();

        $spud = $sourceProductUserDataRepository->find(Config::getCurrentUserId(), $sourceProductId);

        if (!$spud) {
            throw new \Exception('SourceProductUserData is not found. Has no access to unlink sourceProduct.');
        }

        $sourceProductRepository->unlinkFromProduct($productId);

        $this->tResponse->setSuccess(true);
    }

    // api call
    public function linkBook(array $data): void
    {
        ArrayHandler::hasParamThroughException('product_id', $data);
        ArrayHandler::hasParamThroughException('book_id', $data);

        $productId = ArrayHandler::getValueAsInt('product_id', $data);
        $bookId = ArrayHandler::getValueAsInt('book_id', $data);
        $productRepository = new ProductRepository();
        $product = $productRepository->find($productId);

        if (!$product) {
            throw new \RuntimeException('Product not found for link book');
        }

        $bookRepository = new BookRepository();
        $bookUserDataRepository = new BookUserDataRepository();

        $bud = $bookUserDataRepository->find(Config::getCurrentUserId(), $bookId);

        if (!$bud) {
            $bookUserDataRepository->create([
                BookUserData::PARAM_USER_ID => Config::getCurrentUserId(),
                BookUserData::PARAM_BOOK_ID => $bookId,
            ]);
        }

        $bookRepository->linkBookToProduct($productId, $bookId);

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'product' => $productRepository->findProduct($product->getShopProductId(), true)->toArray(),
        ]);
    }

    // api call
    public function unlinkBook(array $data): void
    {
        ArrayHandler::hasParamThroughException('product_id', $data);
        ArrayHandler::hasParamThroughException('book_id', $data);

        $productId = ArrayHandler::getValueAsInt('product_id', $data);
        $bookId = ArrayHandler::getValueAsInt('book_id', $data);
        $productRepository = new ProductRepository();
        $product = $productRepository->find($productId);

        if (!$product) {
            throw new \RuntimeException('Product is not found for unlink book');
        }

        $bookRepository = new BookRepository();
        $bookUserDataRepository = new BookUserDataRepository();

        $bud = $bookUserDataRepository->find(Config::getCurrentUserId(), $bookId);

        if (!$bud) {
            throw new \Exception('BookUserData is not found. Has no access to unlink book.');
        }

        $bookRepository->unlinkBookFromProduct($productId);

        $this->tResponse->setSuccess(true);
    }

    // api call
    public function getBooksByTitle(array $data): void
    {
        ArrayHandler::hasParamThroughException(Book::PARAM_TITLE, $data, 'Not found title');
        $bookRepository = new BookRepository();

        $result = $bookRepository->getBooksByTitle(ArrayHandler::getValueAsString(Book::PARAM_TITLE, $data));

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

        $sourceProductRepository = new SourceProductRepository();

        $result = $sourceProductRepository->getSourceProductsByTitle(
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

        $productRepository = new ProductRepository();

        $shopProductId = ArrayHandler::getValueAsString(Product::PARAM_SHOP_PRODUCT_ID, $data);
        $product = $productRepository->findProduct($shopProductId, true);

        if ($product) {
            $productUserDataRepository = new ProductUserDataRepository();
            $isArchive = ArrayHandler::getValueAsBool(ProductUserData::PARAM_IS_ARCHIVE, $data);

            QueryPdo::beginTransaction();
            try {
                if (!$product->getProductUserData()) {
                    $productUserDataRepository->save([
                        ProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
                        ProductUserData::PARAM_PRODUCT_ID => $product->getId(),
                        ProductUserData::PARAM_AVAILABLE => true,
                        ProductUserData::PARAM_IS_ARCHIVE => $isArchive,
                    ]);

                    $pud = $productUserDataRepository->find(
                        Config::getCurrentUserid(),
                        $product->getId()
                    );

                    $product->setProductUserData($pud);
                } else {
                    $productUserDataRepository->changeIsArchive($product->getId(), $isArchive);
                    $product->getProductUserData()->setIsArchive($isArchive);
                }

                if ($product->getBook() && !$product->getBook()->getBookUserData()) {
                    $bookUserDataRepository = new BookUserDataRepository();

                    $bookUserDataRepository->save([
                        BookUserData::PARAM_USER_ID => Config::getCurrentUserid(),
                        BookUserData::PARAM_BOOK_ID => $product->getBook()->getId(),
                    ]);

                    $bud = $bookUserDataRepository->find(
                        Config::getCurrentUserid(),
                        $product->getBook()->getId()
                    );

                    $product->getBook()->setBookUserData($bud);
                }

                if ($product->getSourceProduct() && !$product->getSourceProduct()->getSourceProductUserData()) {
                    $sourceProductUserDataRepository = new SourceProductUserDataRepository();

                    $sourceProductUserDataRepository->save([
                        SourceProductUserData::PARAM_USER_ID => Config::getCurrentUserid(),
                        SourceProductUserData::PARAM_SOURCE_PRODUCT => $product->getSourceProduct()->getId(),
                    ]);

                    $spud = $sourceProductUserDataRepository->find(
                        Config::getCurrentUserid(),
                        $product->getSourceProduct()->getId()
                    );

                    $product->getSourceProduct()->setSourceProductUserData($spud);
                }

                QueryPdo::commit();
            } catch (\Throwable $e) {
                QueryPdo::rollBack();

                throw $e;
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
        $stockRepository = new StockRepository();
        $foundStock = $stockRepository->getStock($stockData);

        if (!$foundStock) {
            throw new \Exception('Stock is not found');
        }

        $countRemoved = $stockRepository->deleteStock($stockData);
        $this->tResponse->setSuccess(true);
        $this->tResponse->setMessage('Stock is removed, affected: ' . $countRemoved);
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

        $items = [];

        if (!empty($productIds)) {
            $productRepository = new ProductRepository();
            $products = $productRepository->getProductsByShopProductIds($productIds);
            $items = array_map(function ($product) {
                return $product->toArray();
            }, $products);
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'items' => $items
        ]);
    }

    // api call
    public function saveBook(array $modelData): void
    {
        $bookRepository = new BookRepository();

        QueryPdo::beginTransaction();
        try {
            $entityId = $bookRepository->save($modelData);

            QueryPdo::commit();
        } catch (\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        /** @var Book $model */
        $model = $bookRepository->find($entityId);

        if (!$model) {
            throw new \Exception('Not found book by id ' . $entityId);
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
        $sourceProductRepository = new SourceProductRepository();

        QueryPdo::beginTransaction();
        try {
            $entityId = $sourceProductRepository->save($modelData);

            QueryPdo::commit();
        } catch (\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        $model = $sourceProductRepository->find($entityId);

        if (!$model) {
            throw new \Exception('Not found SourceProduct by id ' . $entityId);
        }

        $this->tResponse->setSuccess(true);
        $this->tResponse->setData([
            'entity' => $model->toArray()
        ]);
        $this->tResponse->setMessage('SourceProduct is saved');
    }

    // api call
    public function saveProduct(array $productData): void
    {
        $productRepository  = new ProductRepository();

        QueryPdo::beginTransaction();
        try {
            $productRepository->saveProduct($productData);

            QueryPdo::commit();
        } catch (\Throwable $e) {
            QueryPdo::rollBack();

            throw $e;
        }

        $model = $productRepository->findProduct(
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
        throw new \RuntimeException('ImportByShopType is not implemented');
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
        $productRepository  = new ProductRepository();

        foreach ($productsData as $productData) {
            $productsCount++;

            try {
                QueryPdo::beginTransaction();
                try {
                    $productRepository->saveProduct($productData);

                    QueryPdo::commit();
                } catch (\Throwable $e) {
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
