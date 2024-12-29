<?php

namespace PullRepository;

use Models\Product;
use Models\SameProduct;
use Repository\PriceDateRepository;
use Repository\SameProductRepository;
use Repository\StockRepository;

class SameProductPullRepository extends AbstractPullRepository
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

        foreach ($sameProductRepository->getRowsByProductIds($this->ids) as $row) {
            $spPrefix = 'source-product-';
            $bookPrefix = 'book-';

            if (isset($row[Product::PARAM_SOURCE_PRODUCT_ID])) {
                $sourceProductId = $row[Product::PARAM_SOURCE_PRODUCT_ID];

                if (!isset($this->pull[$spPrefix . $sourceProductId])) {
                    $this->pull[$spPrefix . $sourceProductId] = [];
                }

                $this->pull[$spPrefix . $sourceProductId][] = SameProduct::create($row);
                continue;
            }

            if (isset($row[Product::PARAM_BOOK_ID])) {
                $bookId = $row[Product::PARAM_BOOK_ID];

                if (!isset($this->pull[$bookPrefix . $bookId])) {
                    $this->pull[$bookPrefix . $bookId] = [];
                }

                $this->pull[$bookPrefix . $bookId][] = SameProduct::create($row);
            }
        }
    }

}