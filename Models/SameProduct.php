<?php

namespace Models;

use Models\Product;

/**
 * @method string getProductId()
 * @method string getCode()
 * @method int getMinPrice()
 * @method bool getAvailable()
 * @method int getBookId()
 *
 * @method Shop getShop()
 */
class SameProduct extends Entity
{
    public const TABLE_PREFIX = 'same_p';
    public const TABLE_NAME = 'products';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        Product::PARAM_PRODUCT_ID => 'ID товара',
        Product::PARAM_CODE => 'Код 1С',
        Product::PARAM_AVAILABLE => 'Доступен',
        Product::PARAM_BOOK_ID => 'ID книги',
    ];

    protected const RELATION_TO_ONE = [
        Product::PARAM_SHOP => [
            'parent_id' => Product::PARAM_SHOP_ID,
            'relation_entity' => Shop::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    protected string $productId;
    protected ?string $code;
    protected bool $available;
    protected int $minPrice;
    protected int $bookId;

    protected Shop $shop;

    public function toArray(): array
    {
        $m = parent::toArray();

        $m[Product::PARAM_SHOP_TYPE] = $this->getShop()->getType();

        unset(
            $m[Product::PARAM_SHOP]
        );

        return $m;
    }
}