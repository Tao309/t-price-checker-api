<?php

namespace Models;

use DateTime;
use Repository\Repository;

/**
 * @method int getTitle()
 * @method string getAuthor()
 * @method string getIsbn()
 * @method int getPages()
 * @method int getCirculation()
 * @method int getSize()
 * @method string getPublishYear()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 * @method int getLivelibId()
 * @method int getGoodreadsId()
 * @method int getFantlabId()
 * @method float getLivelibRating()
 * @method float getGoodreadsRating()
 *
 * @method BindingType getBindingType()
 * @method BookUserData getBookUserData()
 * @method User getAuthorUser()
 *
 * @method self setTitle(string $value)
 * @method self setAuthor(string $value)
 * @method self setIsbn(string $value)
 * @method self setPages(int $value)
 * @method self setCirculation(int $value)
 * @method self setSize(int $value)
 * @method self setPublishYear(int $value)
 * @method self setLivelibId(int $value)
 * @method self setGoodreadsId(int $value)
 * @method self setFantlabId(int $value)
 * @method self setLivelibRating(int $value)
 * @method self setGoodreadsRating(int $value)
 *
 * @method self setBookUserData(BookUserData $value)
 */
class Book extends Entity
{
    public const TABLE_PREFIX = 'b';
    public const TABLE_NAME = 'books';

    public const PARAM_TITLE = 'title';
    public const PARAM_AUTHOR = 'author';
    public const PARAM_ISBN = 'isbn';
    public const PARAM_PAGES = 'pages';
    public const PARAM_CIRCULATION = 'circulation'; // тираж.
    public const PARAM_SIZE = 'size'; // Размер.
    public const PARAM_BINDING_TYPE_ID = 'binding_type_id';
    public const PARAM_PUBLISH_YEAR = 'publish_year';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_LIVELIB_ID = 'livelib_id';
    public const PARAM_GOODREADS_ID = 'goodreads_id';
    public const PARAM_FANTLAB_ID = 'fantlab_id';

    public const PARAM_LIVELIB_RATING = 'livelib_rating';
    public const PARAM_GOODREADS_RATING = 'goodreads_rating';

    // От зависимых моделей.
    public const PARAM_BINDING_TYPE = 'binding_type';
    public const PARAM_BOOK_USER_DATA = 'book_user_data';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_TITLE => 'Название',
        self::PARAM_AUTHOR => 'Автор',
        self::PARAM_ISBN => 'ISBN',
        self::PARAM_PAGES => 'Количество страниц',
        self::PARAM_CIRCULATION => 'Тираж',
        self::PARAM_SIZE => 'Размер',
        self::PARAM_PUBLISH_YEAR => 'Год публикации',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
        self::PARAM_LIVELIB_ID => 'ID livelib',
        self::PARAM_GOODREADS_ID => 'ID goodreads',
        self::PARAM_FANTLAB_ID => 'ID fantlab',
        self::PARAM_LIVELIB_RATING => 'Рейтинг livelib',
        self::PARAM_GOODREADS_RATING => 'Рейтинг goodreads',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];

    protected const WHEN_CREATE_REQUIRED_PROPERTIES = [
        self::PARAM_TITLE,
        self::PARAM_AUTHOR,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_BINDING_TYPE => [
            Repository::PARAM_PARENT_ID => self::PARAM_BINDING_TYPE_ID,
            Repository::PARAM_RELATION_ENTITY => BindingType::class,
            Repository::PARAM_RELATION_ID => Entity::PARAM_ID,
        ],
        self::PARAM_BOOK_USER_DATA => [
            Repository::PARAM_PARENT_ID => Entity::PARAM_ID,
            Repository::PARAM_RELATION_ENTITY => BookUserData::class,
            Repository::PARAM_RELATION_ID => BookUserData::PARAM_BOOK_ID,
            Repository::PARAM_RELATION_USER_ID => BookUserData::PARAM_USER_ID,
        ],
        Entity::PARAM_AUTHOR_USER => [
            'parent_id' => Entity::PARAM_AUTHOR_USER_ID,
            'relation_entity' => User::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    protected string $title;
    protected ?string $author;
    protected ?string $isbn;
    protected ?int $pages;
    protected ?int $circulation;
    protected ?string $size;
    protected ?int $publishYear;
    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;
    protected ?int $livelibId;
    protected ?int $goodreadsId;
    protected ?int $fantlabId;
    protected ?float $livelibRating;
    protected ?float $goodreadsRating;

    // Приватные свойства не попадают в обходе у родителя. __call в родителе.
    protected ?User $authorUser = null;
    protected ?BookUserData $bookUserData = null;
    protected ?BindingType $bindingType = null;

    public function getUser(): User
    {
        return $this->getBookUserData()->getUser();
    }

    public function getReleaseDate(): DateTime
    {
        return $this->getBookUserData()->getReleaseDate();
    }

    public function getListenPriceValue(): int
    {
        return $this->getBookUserData()->getListenPriceValue();
    }

    public function getComment(): string
    {
        return $this->getBookUserData()->getComment();
    }
}
