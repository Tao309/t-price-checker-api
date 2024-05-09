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

    protected array $relationToOne = [
        self::PARAM_BINDING_TYPE => BindingType::class
    ];
}