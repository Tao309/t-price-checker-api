<?php

namespace PullRepository;

use Models\Product;
use Models\SameProduct;
use Repository\SameProductRepository;

/**
 * @method SameProduct[] getFromPull($id)
 */
class SameProductBySourceProductPullRepository extends AbstractPullRepository
{
    protected array $pull = [];

    private array $ids = [];

    public function __construct(array $ids)
    {
        $this->ids = $ids;

        parent::__construct();
    }

    protected function fillPull(): void
    {
        $sameProductRepository = new SameProductRepository();

        foreach ($sameProductRepository->getRowsBySourceProductIds($this->ids) as $row) {
            if (isset($row[Product::PARAM_SOURCE_PRODUCT_ID])) {
                $sourceProductId = $row[Product::PARAM_SOURCE_PRODUCT_ID];

                if (!isset($this->pull[$sourceProductId])) {
                    $this->pull[$sourceProductId] = [];
                }

                $this->pull[$sourceProductId][] = SameProduct::create($row);
            }
        }
    }
}
