<?php

namespace Models;

use DateTime;

/**
 * @method DateTime getDate()
 * @method int getPrice()
 *
 * @method User getUser()
 */
class PriceDate extends Entity
{
    public const TABLE_PREFIX = 'pd';
    public const TABLE_NAME = 'products_dates';

    public const PARAM_DATE = 'date';
    public const PARAM_PRICE = 'price';
    public const PARAM_USER_ID = 'user_id';

    // От зависимых моделей.
    public const PARAM_USER = 'user';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_DATE => 'Дата',
        self::PARAM_PRICE => 'Цена',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_USER_ID,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_USER => [
            'parent_id' => self::PARAM_USER_ID,
            'relation_entity' => User::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    protected DateTime $date;
    protected int $price;

    // Приватные свойства не попадают в обходе у родителя. __call в родителе.
    protected User $user;
}