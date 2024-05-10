<?php

namespace Models;

/**
 * @method string getType()
 * @method string getDomain()
 * @method string getUrl()
 */
class Shop extends Entity
{
    public const TABLE_PREFIX = 's';
    public const TABLE_NAME = 'shops';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_TYPE => 'Тип',
        self::PARAM_DOMAIN => 'Домен',
        self::PARAM_URL => 'Урл',
    ];

    public const PARAM_TYPE = 'type';
    public const PARAM_DOMAIN = 'domain';
    public const PARAM_URL = 'url';

    protected string $type;
    protected string $domain;
    protected ?string $url;
}