<?php

namespace Models;

use DateTime;

/**
 * @method int getUserId()
 * @method int getProductId()
 * @method bool getAvailable()
 * @method null|DateTime getNotAvailableDateFrom()
 * @method null|DateTime getAvailableDateFrom()
 * @method null|int getListenPriceValue()
 * @method null|int getListenQtyValue()
 * @method bool getIsArchive()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 *
 * @method User getUser()
 *
 * @method setAvailable(bool $value)
 * @method setNotAvailableDateFrom(string $value)
 * @method setAvailableDateFrom(string $value)
 * @method setListenPriceValue(int $value)
 * @method setListenQtyValue(int $value)
 * @method setIsArchive(bool $value)
 */
class ProductUserData extends Entity
{
    public const TABLE_PREFIX = 'pud';
    public const TABLE_NAME = 'product_user_data';

    public const PARAM_USER_ID = 'user_id';
    public const PARAM_PRODUCT_ID = 'product_id';

    public const PARAM_AVAILABLE = 'available';
    public const PARAM_NOT_AVAILABLE_DATE_FROM = 'not_available_date_from';
    public const PARAM_AVAILABLE_DATE_FROM = 'available_date_from';
    public const PARAM_LISTEN_PRICE_VALUE = 'listen_price_value';
    public const PARAM_LISTEN_QTY_VALUE = 'listen_qty_value';
    public const PARAM_IS_ARCHIVE = 'is_archive';

    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_USER = 'user';

    protected const PRIMARY_KEY = [self::PARAM_USER_ID, self::PARAM_PRODUCT_ID];

    protected const PROPERTIES = [
        self::PARAM_USER_ID => 'ID пользователя',
        self::PARAM_PRODUCT_ID => 'ID продукта',
        self::PARAM_AVAILABLE => 'Доступен',
        self::PARAM_NOT_AVAILABLE_DATE_FROM => 'Недоступен с',
        self::PARAM_AVAILABLE_DATE_FROM => 'Доступен с',
        self::PARAM_LISTEN_PRICE_VALUE => 'Отслеживание цены',
        self::PARAM_LISTEN_QTY_VALUE => 'Отслеживание количества',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_USER_ID,
        self::PARAM_PRODUCT_ID,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];


    protected const RELATION_TO_ONE = [
        self::PARAM_USER => [
            'parent_id' => self::PARAM_USER_ID,
            'relation_entity' => User::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    protected int $userId;
    protected int $productId;

    protected bool $available;
    protected ?DateTime $notAvailableDateFrom = null;
    protected ?DateTime $availableDateFrom = null;
    protected ?int $listenPriceValue = null;
    protected ?int $listenQtyValue = null;
    protected bool $isArchive;

    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;

    protected User $user;
}