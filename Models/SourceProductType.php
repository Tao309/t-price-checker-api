<?php

namespace Models;

/**
 * @method string getCode()
 * @method string getName()
 * @method string getDateCreated()
 */
class SourceProductType extends Entity
{
    public const TABLE_PREFIX = 'spt';
    public const TABLE_NAME = 'source_product_types';

    public const PARAM_CODE = 'code';
    public const PARAM_NAME = 'name';
    public const PARAM_DATE_CREATED = 'date_created';

    public const CODE_BOOKS = 'books';
    public const CODE_SHOES = 'shoes';
    public const CODE_SHIRTS = 'shirts';
    public const CODE_BEVERAGES = 'beverages';
    public const CODE_MEDICINES = 'medicines';
    public const CODE_SHORTS = 'shorts';

    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_DATE_CREATED,
    ];

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_CODE => 'Код',
        self::PARAM_NAME => 'Название',
        self::PARAM_DATE_CREATED => 'Дата создания'
    ];

    protected string $code;
    protected string $name;
    protected string $dateCreated;
}