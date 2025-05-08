<?php

namespace Models;

use DateTime;

/**
 * @method string getName()
 */
class PublishingHouse extends Entity
{
    public const TABLE_PREFIX = 'bph';
    public const TABLE_NAME = 'book_publishing_house';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_NAME => 'Название',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    public const PARAM_NAME = 'name';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    protected string $name;
    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;
}