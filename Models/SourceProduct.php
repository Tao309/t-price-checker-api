<?php

namespace Models;

use DateTime;

/**
 * @method string getTitle()
 * @method ?int getListenPriceValue()
 * @method ?string getComment()
 * @method DateTime getDateCreated()
 *
 * @method SourceProductType getSourceProductType()
 *
 * @method self setTitle(string $value)
 * @method self setListenPriceValue(int $value)
 * @method self setComment(string $value)
 */
class SourceProduct extends Entity
{
    public const TABLE_PREFIX = 'sp';
    public const TABLE_NAME = 'source_products';

    public const PARAM_SOURCE_PRODUCT_TYPE_ID = 'source_product_type_id';
    public const PARAM_TITLE = 'title';
    public const PARAM_LISTEN_PRICE_VALUE = 'listen_price_value';
    public const PARAM_COMMENT = 'comment';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_SOURCE_PRODUCT_TYPE = 'source_product_type';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_TITLE => 'Название',
        self::PARAM_LISTEN_PRICE_VALUE => 'Отслеживание цены',
        self::PARAM_COMMENT => 'Комментарий',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_DATE_CREATED,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_SOURCE_PRODUCT_TYPE => [
            'parent_id' => self::PARAM_SOURCE_PRODUCT_TYPE_ID,
            'relation_entity' => SourceProductType::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    protected string $title;
    protected ?int $listenPriceValue;
    protected ?string $comment;
    protected DateTime $dateCreated;

    protected SourceProductType $sourceProductType;
}