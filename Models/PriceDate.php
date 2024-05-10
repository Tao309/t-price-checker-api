<?php

namespace Models;

use DateTime;

/**
 * @method DateTime getDate()
 * @method int getPrice()
 */
class PriceDate extends Entity
{
    public const TABLE_PREFIX = 'pd';
    public const TABLE_NAME = 'products_dates';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_DATE => 'Дата',
        self::PARAM_PRICE => 'Цена',
    ];

    public const PARAM_DATE = 'date';
    public const PARAM_PRICE = 'price';

    protected DateTime $date;
    protected string $price;
}