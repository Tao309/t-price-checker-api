<?php

class tResponse {
    public const PARAM_SUCCESS = 'success';
    public const PARAM_MESSAGE = 'message';
    public const PARAM_DATA = 'data';

    public const MESSAGE_ACCESS_LIMITED = 'Access limited';

    private const AVAILABLE_REQUEST_METHODS = [
        'saveProduct',
        'saveProducts',
        'deleteProduct',
        'getProductsByShopType',
        'importByShopType', // нужен только в начале
        'getBooksByTitle',
        'saveBook',
        'removeStock',
    ];

    private bool $success = false;
    private ?string $message = null;
    private array $data = [];

    public function __construct()
    {

    }

    public function __toString()
    {
        return json_encode([
            self::PARAM_SUCCESS => $this->isSuccess(),
            self::PARAM_MESSAGE => $this->getMessage(),
            self::PARAM_DATA => $this->getData()
        ], true);
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