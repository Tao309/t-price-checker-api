<?php

namespace Models;

use DateTime;

/**
 * @method string getTitle()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 *
 * @method SourceProductType getSourceProductType()
 * @method SourceProductUserData getSourceProductUserData()
 * @method User getAuthorUser()
 *
 * @method self setTitle(string $value)
 *
 * @method setSourceProductUserData(SourceProductUserData $model)
 */
class SourceProduct extends Entity
{
    public const TABLE_PREFIX = 'sp';
    public const TABLE_NAME = 'source_products';

    public const PARAM_SOURCE_PRODUCT_TYPE_ID = 'source_product_type_id';
    public const PARAM_TITLE = 'title';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    // От зависимых моделей.
    public const PARAM_SOURCE_PRODUCT_TYPE = 'source_product_type';
    public const PARAM_SOURCE_PRODUCT_USER_DATA = 'source_product_user_data';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_TITLE => 'Название',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_SOURCE_PRODUCT_TYPE => [
            'parent_id' => self::PARAM_SOURCE_PRODUCT_TYPE_ID,
            'relation_entity' => SourceProductType::class,
            'relation_id' => Entity::PARAM_ID,
//            'foreign' => true,
        ],
        self::PARAM_SOURCE_PRODUCT_USER_DATA => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => SourceProductUserData::class,
            'relation_id' => SourceProductUserData::PARAM_SOURCE_PRODUCT,
            'relation_user_id' => SourceProductUserData::PARAM_USER_ID,
        ],
        Entity::PARAM_AUTHOR_USER => [
            'parent_id' => Entity::PARAM_AUTHOR_USER_ID,
            'relation_entity' => User::class,
            'relation_id' => Entity::PARAM_ID,
//            'foreign' => true,
        ],
    ];

    protected string $title;
    protected ?int $authorUserId;
    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;

    // Приватные свойства не попадают в обходе у родителя. __call в родителе.
    protected ?SourceProductUserData $sourceProductUserData = null;
    protected SourceProductType $sourceProductType;
    protected ?User $authorUser = null;
}