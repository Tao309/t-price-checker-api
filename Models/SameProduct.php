<?php

namespace Models;

class SameProduct extends Entity
{
    public const PARAM_PRODUCT_ID = 'product_id';
    public const PARAM_SHOP_TYPE = 'shop_type';
    public const PARAM_PRICE = 'price';
    public const PARAM_BOOK_ID = 'book_id';
    public const PARAM_AVAILABLE = 'available';

    protected string $productId;
    protected string $shopType;
    protected int $price;
    protected int $bookId;
    protected bool $available;

    public const RECORDABLE_PARAMS = [
        self::PARAM_PRODUCT_ID,
        self::PARAM_SHOP_TYPE,
        self::PARAM_PRICE
    ];

    public const RECORDABLE_BOOLEAN_PARAMS = [
        self::PARAM_AVAILABLE
    ];

    public function __construct(array $data)
    {
        parent::__construct($data);
    }
}