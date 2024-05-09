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
}