<?php

namespace PullRepository;

use Models\Product;
use Models\SameProduct;
use Repository\SameProductRepository;

/**
 * @method SameProduct[] getFromPull($id)
 */
class SameProductPullRepository extends AbstractPullRepository
{
    public const SP_PREFIX = 'source-product-';
    public const BOOK_PREFIX = 'book-';

    protected array $pull = [];

    private array $ids = [];

    public function __construct(array $ids)
    {
        $this->ids = $ids;

        parent::__construct();
    }

    public function getFromPullSortMin(Product $product, string $findSameProductId): array
    {
        $sameProducts = $this->getFromPull($findSameProductId);

        $sameProductByShop = null;

        foreach ($sameProducts as $index => $sameProduct) {
            $toUnlink = false;

            if ($sameProduct->getShopProductId() === $product->getShopProductId()) {
                $toUnlink = true;
            } else if ($sameProduct->getShop()->getId() === $product->getShop()->getId()) {
                // Сортировка по увеличению цены, и первый по магазину будет с минимальной ценой.
                if (!$sameProductByShop) {
                    $sameProductByShop = $sameProduct;
                }

                $toUnlink = true;
            }

            if ($toUnlink) {
                unset($sameProducts[$index]);
            }
        }

        if ($sameProductByShop) {
            array_unshift($sameProducts, $sameProductByShop);
        }

        return $sameProducts;

    }

    protected function fillPull(): void
    {
        $sameProductRepository = new SameProductRepository();

        foreach ($sameProductRepository->getRowsByProductIds($this->ids) as $row) {
            if (isset($row[Product::PARAM_SOURCE_PRODUCT_ID])) {
                $sourceProductId = $row[Product::PARAM_SOURCE_PRODUCT_ID];

                if (!isset($this->pull[self::SP_PREFIX . $sourceProductId])) {
                    $this->pull[self::SP_PREFIX . $sourceProductId] = [];
                }

                $this->pull[self::SP_PREFIX . $sourceProductId][] = SameProduct::create($row);
                continue;
            }

            if (isset($row[Product::PARAM_BOOK_ID])) {
                $bookId = $row[Product::PARAM_BOOK_ID];

                if (!isset($this->pull[self::BOOK_PREFIX . $bookId])) {
                    $this->pull[self::BOOK_PREFIX . $bookId] = [];
                }

                $this->pull[self::BOOK_PREFIX . $bookId][] = SameProduct::create($row);
            }
        }
    }

}