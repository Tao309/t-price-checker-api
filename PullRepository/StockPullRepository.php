<?php

namespace PullRepository;

use Repository\PriceDateRepository;
use Repository\StockRepository;

class StockPullRepository extends AbstractPullRepository
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
        $stockRepository = new StockRepository();

        foreach ($stockRepository->getByProductIds($this->ids) as $stock) {
            if (!isset($this->pull[$stock->getId()])) {
                $this->pull[$stock->getId()] = [];
            }

            $this->pull[$stock->getId()][] = $stock;
        }
    }

}