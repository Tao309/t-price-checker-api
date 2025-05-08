<?php

namespace PullRepository;

use Models\Product;

abstract class AbstractPullRepository
{
    protected array $pull = [];

    abstract protected function fillPull();

    public function __construct()
    {
        $this->fillPull();
    }

    public function getFromPull($id): array
    {
        return $this->pull[$id] ?? [];
    }

    public function getFromPullSortMin(Product $product, int $findKeyId): array
    {
        $sameProducts = $this->getFromPull($findKeyId);

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
}
