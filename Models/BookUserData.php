<?php

namespace Models;

use DateTime;

/**
 * @method int getUserId()
 * @method int getBookId()
 * @method DateTime getReleaseDate()
 * @method int getListenPriceValue()
 * @method string getComment()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 *
 * @method User getUser()
 *
 * @method self setReleaseDate(DateTime $value)
 * @method self setListenPriceValue(int $value)
 * @method self setComment(string $value)
 */
class BookUserData extends Entity
{
    public const TABLE_PREFIX = 'bud';
    public const TABLE_NAME = 'book_user_data';

    public const PARAM_USER_ID = 'user_id';
    public const PARAM_BOOK_ID = 'book_id';

    public const PARAM_RELEASE_DATE = 'release_date';
    public const PARAM_LISTEN_PRICE_VALUE = 'listen_price_value';
    public const PARAM_COMMENT = 'comment';

    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_USER = 'user';

    protected const PRIMARY_KEY = [self::PARAM_USER_ID, self::PARAM_BOOK_ID];

    protected const PROPERTIES = [
        self::PARAM_USER_ID => 'ID пользователя',
        self::PARAM_BOOK_ID => 'ID книги',
        self::PARAM_RELEASE_DATE => 'Дата выпуска',
        self::PARAM_LISTEN_PRICE_VALUE => 'Отслеживание цены',
        self::PARAM_COMMENT => 'Комментарий',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_BOOK_ID,
        self::PARAM_USER_ID,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_USER => [
            'parent_id' => self::PARAM_USER_ID,
            'relation_entity' => User::class,
            'relation_id' => Entity::PARAM_ID,
//            'foreign' => true,
        ],
    ];

    protected int $userId;
    protected int $bookId;

    protected DateTime $releaseDate;
    protected ?int $listenPriceValue;
    protected ?string $comment;

    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;

    protected User $user;
}
