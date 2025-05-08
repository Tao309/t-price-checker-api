<?php

namespace Models;

use DateTime;

/**
 * @method DateTime getDate()
 * @method int getQty()
 * @method string|null getLog()
 *
 * @method User getUser()
 */
class Stock extends Entity
{
    public const TABLE_PREFIX = 'ps';
    public const TABLE_NAME = 'products_stocks';

    public const PARAM_DATE = 'date';
    public const PARAM_QTY = 'qty';
    public const PARAM_USER_ID = 'user_id';
    public const PARAM_LOG = 'log';

    // От зависимых моделей.
    public const PARAM_USER = 'user';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_DATE => 'Дата',
        self::PARAM_QTY => 'Количество',
        self::PARAM_LOG => 'Лог',
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
    protected int $qty;
    protected ?string $log;

    // Приватные свойства не попадают в обходе у родителя. __call в родителе.
    protected User $user;
}