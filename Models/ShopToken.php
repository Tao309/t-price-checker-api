<?php

namespace Models;

use Repository\Repository;

/**
 * @method int getUserId()
 * @method int getShopId()
 * @method string getToken()
 *
 * @method User getUser()
 * @method Shop getShop()
 */
class ShopToken extends Entity
{
    public const TABLE_PREFIX = 'st';
    public const TABLE_NAME = 'shop_token';

    protected const PRIMARY_KEY = [self::PARAM_USER_ID, self::PARAM_SHOP_ID];

    protected const PROPERTIES = [
        self::PARAM_USER_ID => 'ID пользователя',
        self::PARAM_SHOP_ID => 'ID магазина',
        self::PARAM_TOKEN => 'Токен маркетплейса',
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_USER => [
            Repository::PARAM_PARENT_ID => self::PARAM_USER_ID,
            Repository::PARAM_RELATION_ENTITY => User::class,
            Repository::PARAM_RELATION_ID => Entity::PARAM_ID,
        ],
        self::PARAM_SHOP => [
            Repository::PARAM_PARENT_ID => self::PARAM_SHOP_ID,
            Repository::PARAM_RELATION_ENTITY => Shop::class,
            Repository::PARAM_RELATION_ID => Entity::PARAM_ID,
        ],
    ];

    public const PARAM_USER_ID = 'user_id';
    public const PARAM_SHOP_ID = 'shop_id';
    public const PARAM_TOKEN = 'token';

    public const PARAM_USER = 'user';
    public const PARAM_SHOP = 'shop';

    protected int $userId;
    protected int $shopId;
    protected string $token;

    protected User $user;
    protected Shop $shop;
}