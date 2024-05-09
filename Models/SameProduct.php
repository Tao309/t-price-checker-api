<?php

namespace Models;

/**
 * @method string getProductId()
 * @method string getCode()
 * @method string getShopType()
 * @method int getMinPrice()
 * @method bool getAvailable()
 */
class SameProduct extends Entity
{
    protected string $productId;
    protected ?string $code;
    protected string $shopType;
    protected int $minPrice;
    protected bool $available;

    public const RECORDABLE_PARAMS = [
        Product::PARAM_PRODUCT_ID,
        Product::PARAM_CODE,
        Product::PARAM_SHOP_TYPE,
        Product::PARAM_MIN_PRICE
    ];

    public const RECORDABLE_BOOLEAN_PARAMS = [
        Product::PARAM_AVAILABLE
    ];
}