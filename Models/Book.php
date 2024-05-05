<?php

namespace Models;

/**
 * @method int getTitle()
 * @method string getAuthor()
 * @method string getIsbn()
 * @method int getPages()
 * @method int getCirculation()
 * @method int getSize()
 * @method string getPublishYear()
 * @method string getDateCreated()
 * @method string getDateUpdated()
 *
 * @method array getBindingType()
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
    public const PARAM_BINDING_TYPE = 'binding_type';
    public const PARAM_BINDING_TYPE_ID = 'book.binding_type.id';
    public const PARAM_BINDING_TYPE_LABEL = 'book.binding_type.label';
    public const PARAM_PUBLISH_YEAR = 'publish_year';
    public const PARAM_RELEASE_DATE = 'release_date';
    public const PARAM_DATE_CREATED = 'date_created';

    protected string $title;
    protected ?string $author;
    protected ?string $isbn;
    protected ?int $pages;
    protected ?int $circulation;
//    protected ?int $bindingType;
    protected ?int $publishYear;
    protected string $releaseDate;
    protected string $dateCreated;

    // Приватные свойства не попадают в обходе у родителя.
    private array $bindingType = [];

    public const RECORDABLE_PARAMS = [
        self::PARAM_TITLE,
        self::PARAM_AUTHOR,
        self::PARAM_ISBN,
        self::PARAM_PAGES,
        self::PARAM_CIRCULATION,
        self::PARAM_SIZE,
//        self::PARAM_BINDING_TYPE,
        self::PARAM_PUBLISH_YEAR,
    ];

    public const RECORDABLE_DATETIME_PARAMS = [
        self::PARAM_RELEASE_DATE,
        self::PARAM_DATE_CREATED
    ];

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (isset($data[self::PARAM_BINDING_TYPE_ID])) {
            $this->bindingType = [
                'id' => $data[self::PARAM_BINDING_TYPE_ID],
                'label' => $data[self::PARAM_BINDING_TYPE_LABEL]
            ];
        }
    }

    public function toArray(): array
    {
        $m = parent::toArray();

        if ($this->bindingType) {
            $m[Book::PARAM_BINDING_TYPE] = $this->bindingType;
        }

        return $m;
    }
}