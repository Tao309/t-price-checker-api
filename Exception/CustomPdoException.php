<?php

namespace Exception;

use Throwable;
use QueryPdo;

class CustomPdoException extends \Exception
{
    private string $requestMethod;
    private QueryPdo $queryPdo;

    public function __construct(
        string $requestMethod,
        QueryPdo $queryPdo,
        Throwable $e
    )
    {
        $this->requestMethod = $requestMethod;
        $this->queryPdo = $queryPdo;

        parent::__construct($e->getMessage(), $e->getCode());
    }

    public function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    public function getQueryPdo(): QueryPdo
    {
        return $this->queryPdo;
    }

}