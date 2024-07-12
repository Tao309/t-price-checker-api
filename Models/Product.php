<?php

namespace Models;

use Core\Config;
use DateTime;

/**
 * @method string getProductId()
 * @method string getCode()
 * @method int getShopId()
 * @method int|null getBookId()
 * @method int getUserId()
 * @method int getTitle()
 * @method bool getAvailable()
 * @method ?DateTime getNotAvailableDateFrom()
 * @method ?DateTime getAvailableDateFrom()
 * @method ?int getListenPriceValue()
 * @method ?int getListenQtyValue()
 * @method ?DateTime getReleaseDate()
 * @method bool getIsArchive()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 *
 * @method Shop getShop()
 * @method Book|null getBook()
 * @method int getMinPrice()
 * @method int getLastQty()
 * @method PriceDate[] getPriceDates()
 * @method Stock[] getStocks()
 * @method SameProduct[] getSameProducts()
 *
 * @method setTitle(string $value)
 * @method setAvailable(bool $value)
 * @method setNotAvailableDateFrom(string $value)
 * @method setAvailableDateFrom(string $value)
 * @method setListenPriceValue(int $value)
 * @method setListenQtyValue(int $value)
 * @method setReleaseDate(string $value)
 */
class Product extends Entity
{
    public const TABLE_PREFIX = 'p';
    public const TABLE_NAME = 'products';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_PRODUCT_ID => 'ID товара',
        self::PARAM_CODE => 'Код 1С',
        self::PARAM_BOOK_ID => 'ID книги',
        self::PARAM_TITLE => 'Название',
        self::PARAM_AVAILABLE => 'Доступен',
        self::PARAM_NOT_AVAILABLE_DATE_FROM => 'Недоступен с',
        self::PARAM_AVAILABLE_DATE_FROM => 'Доступен с',
        self::PARAM_LISTEN_PRICE_VALUE => 'Отслеживание цены',
        self::PARAM_LISTEN_QTY_VALUE => 'Отслеживание количества',
        self::PARAM_RELEASE_DATE => 'Дата выпуска',
        self::PARAM_IS_ARCHIVE => 'В архиве',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_PRODUCT_ID,
        self::PARAM_SHOP_ID,
        self::PARAM_USER_ID,
//        self::PARAM_NOT_AVAILABLE_DATE_FROM,
//        self::PARAM_AVAILABLE_DATE_FROM,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_BOOK => [
            'parent_id' => self::PARAM_BOOK_ID,
            'relation_entity' => Book::class,
            'relation_id' => Entity::PARAM_ID
        ],
        self::PARAM_SHOP => [
            'parent_id' => self::PARAM_SHOP_ID,
            'relation_entity' => Shop::class,
            'relation_id' => Entity::PARAM_ID,
            'foreign' => true,
        ]
    ];

    protected const RELATION_TO_MANY = [
        self::PARAM_PRICE_DATES => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => PriceDate::class
        ],
        self::PARAM_STOCKS => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => Stock::class
        ],
        self::PARAM_SAME_PRODUCTS => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => SameProduct::class
        ]
    ];

    public const PARAM_PRODUCT_ID = 'product_id';
    public const PARAM_CODE = 'code';
    public const PARAM_BOOK_ID = 'book_id';
    public const PARAM_SHOP_ID = 'shop_id';
    public const PARAM_SHOP_TYPE = 'shop_type';
    public const PARAM_USER_ID = 'user_id';
    public const PARAM_TITLE = 'title';
    public const PARAM_AVAILABLE = 'available';
    public const PARAM_NOT_AVAILABLE_DATE_FROM = 'not_available_date_from';
    public const PARAM_AVAILABLE_DATE_FROM = 'available_date_from';
    public const PARAM_STOCKS = 'stocks';
    public const PARAM_PRICE_DATES = 'price_dates';
    public const PARAM_LISTEN_PRICE_VALUE = 'listen_price_value';
    public const PARAM_LISTEN_QTY_VALUE = 'listen_qty_value';
    public const PARAM_RELEASE_DATE = 'release_date';
    public const PARAM_IS_ARCHIVE = 'is_archive';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_MIN_PRICE = 'min_price';
    public const PARAM_LAST_QTY = 'last_qty';

    public const PARAM_FLAGS = 'flags';
    public const PARAM_BOOK = 'book';
    public const PARAM_SHOP = 'shop';
    public const PARAM_SAME_PRODUCTS = 'same_products';

    public const FLAG_TO_SAVE_PRODUCT = 'flag_to_save_product';
    public const FLAG_TO_SAVE_PRICE_DATES = 'flag_to_save_price_dates';
    public const FLAG_TO_SAVE_STOCKS = 'flag_to_save_stocks';
    public const FLAG_TO_LINK_BOOK = 'flag_to_link_book';
    public const FLAG_TO_UNLINK_BOOK = 'flag_to_unlink_book';
    public const FLAG_TO_CHANGE_ID = 'flag_to_change_id';

    protected int $productId;
    protected ?string $code;
    protected int $shopId;
//    protected string $shopType;
//    protected int $userId;
    protected ?int $bookId;
    protected string $title;
    protected bool $available;
    protected ?DateTime $notAvailableDateFrom;
    protected ?DateTime $availableDateFrom;
    protected ?int $listenPriceValue;
    protected ?int $listenQtyValue;
    protected ?DateTime $releaseDate;
    protected bool $isArchive;
    protected DateTime $dateCreated;
    protected DateTime $dateUpdated;

    protected Shop $shop;
    protected ?Book $book = null;
    protected ?int $minPrice = null;
    protected ?int $lastQty = null;
    /** @var PriceDate[] */
    protected array $priceDates = [];
    /** @var Stock[] */
    protected array $stocks = [];
    /** @var SameProduct[] */
    protected array $sameProducts = [];

    public function __construct(array $data)
    {
        parent::__construct($data);

        if ($this->getPriceDates()) {
            $this->minPrice = min(
                array_map(function($priceDate) {
                    return $priceDate->getPrice();
                }, $this->getPriceDates())
            );
        }

        if ($this->getStocks()) {
            $stocks = $this->getStocks();
            $this->lastQty = end($stocks)->getQty();
        }
    }

    public function toArray(): array
    {
        $m = parent::toArray();

        $m[self::PARAM_SHOP_TYPE] = $this->getShop()->getType();

        unset(
            $m[self::PARAM_SHOP]
        );

        return $m;
    }

    public function getUrl(): string|null
    {
        switch ($this->getShop()?->getType()) {
            case Config::TYPE_KNIGOFAN:
                return 'https://knigofan.ru/catalog/horus-heresy/primarkhi/929/';
            case Config::TYPE_WILDBERRIES:
                if ($this->getCode()) {
                    return 'https://www.wildberries.ru/catalog/' . $this->getCode() . '/detail.aspx?size=' . $this->getProductId();
                }

                return 'https://www.wildberries.ru/catalog/' . $this->getProductId() . '/detail.aspx';
            case Config::TYPE_OZON:
                return 'https://www.ozon.ru/product/' . $this->getProductId() . '/';
            case Config::TYPE_FFAN:
                return 'https://ffan.ru/catalog/product/' . $this->getProductId() . '/';
            case Config::TYPE_CHITAI_GOROD:
                return 'https://www.chitai-gorod.ru/product/-' . $this->getProductId();
            default:
                return null;
        }
    }

    /**
     * Получаем последний сток.
     *
     * @return Stock|null Модель стока.
     */
    public function getLastStock(): Stock|null
    {
        $stocks = $this->getStocks();

        return end($stocks) ?: null;
    }
}