<?php

use Models\Entity;

class tResponse {
    public const PARAM_SUCCESS = 'success';
    public const PARAM_MESSAGE = 'message';
    public const PARAM_TRACE = 'trace';
    public const PARAM_PREVIOUS_TRACE = 'previous_trace';
    public const PARAM_DATA = 'data';

    public const MESSAGE_ACCESS_LIMITED = 'Access limited';

    private const AVAILABLE_REQUEST_METHODS = [
        'saveProduct',
        'saveProducts',
        'deleteProduct',
        'getProductByShopType',
        'getProductsByShopType',
        'importByShopType', // нужен только в начале
        'getBooksByTitle',
        'saveBook',
        'removeStock',
        'saveSourceProduct',
        'getSourceProductsByTitle',
        'changeProductIsArchive',
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
            throw new RuntimeException(self::MESSAGE_ACCESS_LIMITED);
        }

        if (!in_array($post['action'], self::AVAILABLE_REQUEST_METHODS)) {
            throw new RuntimeException(tResponse::MESSAGE_ACCESS_LIMITED);
        }
    }


    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @param bool $success
     */
    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @param string|null $message
     */
    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return string|null
     */
    public function getTrace(): ?string
    {
        return $this->trace;
    }

    /**
     * @param string|null $trace
     */
    public function setTrace(?string $trace): void
    {
        $this->trace = $trace;
    }

    /**
     * @return string|null
     */
    public function getPreviousTrace(): ?string
    {
        return $this->previousTrace;
    }

    /**
     * @param string|null $trace
     */
    public function setPreviousTrace(?string $trace): void
    {
        $this->previousTrace = $trace;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}