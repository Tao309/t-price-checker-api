<?php

namespace PullRepository;

use Repository\PriceDateRepository;

class PriceDatePullRepository extends AbstractPullRepository
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
        $priceDateRepository = new PriceDateRepository();

        foreach ($priceDateRepository->getByProductIds($this->ids) as $priceDate) {
            if (!isset($this->pull[$priceDate->getId()])) {
                $this->pull[$priceDate->getId()] = [];
            }

            $this->pull[$priceDate->getId()][] = $priceDate;
        }
    }

}