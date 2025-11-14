<?php

namespace Core;

use Exception\NoRightsException;
use Models\Entity;

class tResponse
{
    public const PARAM_SUCCESS = 'success';
    public const PARAM_MESSAGE = 'message';
    public const PARAM_TRACE = 'trace';
    public const PARAM_PREVIOUS_TRACE = 'previous_trace';
    public const PARAM_DATA = 'data';

    public const MESSAGE_ACCESS_LIMITED = 'Access limited';

    private const AVAILABLE_REQUEST_METHODS = [
        'linkSourceProduct',
        'unlinkSourceProduct',
        'linkBook',
        'unlinkBook',
        'saveProduct',
        'saveProducts',
        'getProductsByShopType',
//        'importByShopType', // нужен только в начале
        'getBooksByTitle',
        'saveBook',
        'removeStock',
        'saveSourceProduct',
        'getSourceProductsByTitle',
        'changeProductIsArchive',
        'createBookPublishingHouse',
        'createBookPublishingBrand',
        'createBookSeries',
    ];

    private bool $success = false;
    private ?string $message = null;
    private ?string $trace = null;
    private ?string $previousTrace = null;
    private array $data = [];

    public function __toString()
    {
        $vars = get_object_vars($this);

        $m = [];
        foreach ($vars as $key => $varData) {
            $m[Entity::toCamelCase($key)] = $varData;
        }

        return json_encode($m, true);
    }

    public function checkPostData(array $post = []): void
    {
        if (empty($post['action']) || empty($post['data']) || empty($post['shop_type'])) {
            throw new NoRightsException(self::MESSAGE_ACCESS_LIMITED);
        }

        if (!in_array($post['action'], self::AVAILABLE_REQUEST_METHODS)) {
            throw new NoRightsException(tResponse::MESSAGE_ACCESS_LIMITED);
        }
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getTrace(): ?string
    {
        return $this->trace;
    }

    public function setTrace(?string $trace): void
    {
        $this->trace = $trace;
    }

    public function getPreviousTrace(): ?string
    {
        return $this->previousTrace;
    }

    public function setPreviousTrace(?string $trace): void
    {
        $this->previousTrace = $trace;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function appendData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
    }
}