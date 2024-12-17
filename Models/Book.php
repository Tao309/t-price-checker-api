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
//        self::PARAM_USER_ID,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_BINDING_TYPE => [
            'parent_id' => self::PARAM_BINDING_TYPE_ID,
            'relation_entity' => BindingType::class,
            'relation_id' => Entity::PARAM_ID,
        ],
        self::PARAM_BOOK_USER_DATA => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => BookUserData::class,
            'relation_id' => BookUserData::PARAM_BOOK_ID,
            'relation_user_id' => BookUserData::PARAM_USER_ID,
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
    protected ?BookUserData $bookUserData = null;
    protected ?BindingType $bindingType = null;
    protected User $user;

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
