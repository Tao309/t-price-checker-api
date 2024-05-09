<?php

namespace Models;

use DateTime;

/**
 * @method DateTime getDate()
 * @method int getPrice()
 */
class PriceDate extends Entity
{
    public const PARAM_DATE = 'date';
    public const PARAM_PRICE = 'price';

    protected DateTime $date;
    protected string $price;
}