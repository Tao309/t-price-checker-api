<?php

namespace Models;

use DateTime;

/**
 * @method DateTime getDate()
 * @method int getQty()
 * @method string|null getLog()
 */
class Stock extends Entity
{
    public const TABLE_PREFIX = 'ps';
    public const TABLE_NAME = 'products_stocks';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_DATE => 'Дата',
        self::PARAM_QTY => 'Количество',
        self::PARAM_LOG => 'Лог',
    ];

    public const PARAM_DATE = 'date';
    public const PARAM_QTY = 'qty';
    public const PARAM_LOG = 'log';

    protected DateTime $date;
    protected string $qty;
    protected ?string $log;
}