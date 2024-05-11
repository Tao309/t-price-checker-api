<?php

namespace Models;

use DateTime;

/**
 * @method int getTitle()
 * @method string getAuthor()
 * @method string getIsbn()
 * @method int getPages()
 * @method int getCirculation()
 * @method int getSize()
 * @method string getPublishYear()
 * @method ?int getListenPriceValue()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 *
 * @method BindingType getBindingType()
 *
 * @method setTitle(string $value)
 * @method setAuthor(string $value)
 * @method setIsbn(string $value)
 * @method setPages(int $value)
 * @method setCirculation(int $value)
 * @method setSize(int $value)
 * @method setPublishYear(int $value)
 */
class Book extends Entity
{
    public const TABLE_PREFIX = 'b';
    public const TABLE_NAME = 'books';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_TITLE => 'Название',
        self::PARAM_AUTHOR => 'Автор',
        self::PARAM_ISBN => 'ISBN',
        self::PARAM_PAGES => 'Количество страниц',
        self::PARAM_CIRCULATION => 'Тираж',
        self::PARAM_SIZE => 'Размер',
        self::PARAM_PUBLISH_YEAR => 'Год публикации',
        self::PARAM_RELEASE_DATE => 'Дата выпуска',
        self::PARAM_LISTEN_PRICE_VALUE => 'Отслеживание цены',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_BINDING_TYPE => [
            'parent_id' => self::PARAM_BINDING_TYPE_ID,
            'relation_entity' => BindingType::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    public const PARAM_TITLE = 'title';
    public const PARAM_AUTHOR = 'author';
    public const PARAM_ISBN = 'isbn';
    public const PARAM_PAGES = 'pages';
    public const PARAM_CIRCULATION = 'circulation'; // тираж.
    public const PARAM_SIZE = 'size'; // Размер.
    public const PARAM_BINDING_TYPE_ID = 'binding_type_id';
    public const PARAM_PUBLISH_YEAR = 'publish_year';
    public const PARAM_RELEASE_DATE = 'release_date';
    public const PARAM_LISTEN_PRICE_VALUE = 'listen_price_value';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_BINDING_TYPE = 'binding_type';

    protected string $title;
    protected ?string $author;
    protected ?string $isbn;
    protected ?int $pages;
    protected ?int $circulation;
    protected ?string $size;
    protected ?int $publishYear;
    protected DateTime $releaseDate;
    protected ?int $listenPriceValue;
    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;

    // Приватные свойства не попадают в обходе у родителя. __call в родителе.
    protected ?BindingType $bindingType = null;
}