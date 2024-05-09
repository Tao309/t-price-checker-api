<?php

namespace Models;

/**
 * @method string getDate()
 * @method int getPrice()
 */
class PriceDate extends Entity
{
    public const PARAM_DATE = 'date';
    public const PARAM_PRICE = 'price';

    public const RECORDABLE_PARAMS = [
        self::PARAM_DATE,
        self::PARAM_PRICE,
    ];

    protected string $date;
    protected string $price;

}