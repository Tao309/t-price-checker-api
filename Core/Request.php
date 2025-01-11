<?php

namespace Core;

class Request
{
    public const METHOD_GET = 'get';
    public const METHOD_POST = 'post';

    private string $method;

    private string $url;

    private array $params;

    private array $body;

    private array $headers;

    private array $cookies;

    private array $response;

    public function send()
    {

    }

    public function getResponse(): array
    {
        return $this->response;

    }

    public function setResponse(array $response): void
    {
        $this->response = $response;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }
}