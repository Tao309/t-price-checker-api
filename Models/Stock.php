<?php

namespace Models;

/**
 * @method string getDate()
 * @method int getQty()
 * @method string|null getLog()
 */
class Stock extends Entity
{
    public const PARAM_DATE = 'date';
    public const PARAM_QTY = 'qty';
    public const PARAM_LOG = 'log';

    public const RECORDABLE_PARAMS = [
        self::PARAM_DATE,
        self::PARAM_QTY,
        self::PARAM_LOG,
    ];

    protected string $date;
    protected string $qty;
    protected ?string $log;
}