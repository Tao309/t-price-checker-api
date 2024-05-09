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
    public const PARAM_DATE = 'date';
    public const PARAM_QTY = 'qty';
    public const PARAM_LOG = 'log';

    protected DateTime $date;
    protected string $qty;
    protected ?string $log;
}