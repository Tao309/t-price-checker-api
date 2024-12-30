<?php

namespace Models;

/**
 * @method string getShopProductId()
 * @method string getShopProductCode()
 * @method int getMinPrice()
 * @method int getBookId()
 * @method int getSourceProductId()
 *
 * @method ProductUserData getProductUserData()
 * @method Shop getShop()
 */
class SameProduct extends Entity
{
    public const TABLE_PREFIX = 'same_p';
    public const TABLE_NAME = 'products';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        Product::PARAM_SHOP_PRODUCT_ID => 'ID товара с магазина',
        Product::PARAM_SHOP_PRODUCT_CODE => 'Код 1С',
        Product::PARAM_BOOK_ID => 'ID книги',
        Product::PARAM_SOURCE_PRODUCT_ID => 'ID источника товара',
    ];

    protected const RELATION_TO_ONE = [
        Product::PARAM_SHOP => [
            'parent_id' => Product::PARAM_SHOP_ID,
            'relation_entity' => Shop::class,
            'relation_id' => Entity::PARAM_ID,
        ],
        Product::PARAM_PRODUCT_USER_DATA => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => ProductUserData::class,
            'relation_id' => ProductUserData::PARAM_PRODUCT_ID,
            'relation_user_id' => ProductUserData::PARAM_USER_ID,
        ],
    ];

    protected string $shopProductId;
    protected ?string $shopProductCode;
    protected ?int $minPrice;
    // Поля ниже нужны для сортировки минимальной цену по магазину, где ищется товар.
    protected ?int $bookId;
    protected ?int $sourceProductId;

    protected ?ProductUserData $productUserData = null;
    protected Shop $shop;
}