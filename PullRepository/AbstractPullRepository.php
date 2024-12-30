<?php

namespace PullRepository;

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
}
