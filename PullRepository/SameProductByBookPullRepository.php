<?php

namespace PullRepository;

use Models\Product;
use Models\SameProduct;
use Repository\SameProductRepository;

/**
 * @method SameProduct[] getFromPull($id)
 */
class SameProductByBookPullRepository extends AbstractPullRepository
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

        foreach ($sameProductRepository->getRowsByBookIds($this->ids) as $row) {
            if (isset($row[Product::PARAM_BOOK_ID])) {
                $bookId = $row[Product::PARAM_BOOK_ID];

                if (!isset($this->pull[$bookId])) {
                    $this->pull[$bookId] = [];
                }

                $this->pull[$bookId][] = SameProduct::create($row);
            }
        }
    }
}
