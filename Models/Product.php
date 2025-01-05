<?php

namespace Models;

use Core\Config;
use DateTime;

/**
 * @method string getShopProductId()
 * @method string getShopProductCode()
 * @method int getShopId()
 * @method int|null getProductTypeId()
 * @method int|null getBookId()
 * @method int|null getSourceProductId()
 * @method int getUserId()
 * @method string getTitle()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 *
 * @method Shop getShop()
 * @method SourceProduct|null getSourceProduct()
 * @method Book|null getBook()
 * @method int getMinPrice()
 * @method int getLastQty()
 * @method ProductUserData getProductUserData()
 *
 * @method PriceDate[] getPriceDates()
 * @method Stock[] getStocks()
 * @method SameProduct[] getSameProducts()
 *
 * @method setTitle(string $value)
 *
 * @method setSourceProduct(SourceProduct|null $model)
 * @method setBook(Book|null $model)
 * @method setProductUserData(null|ProductUserData $model)
 * @method setSameProducts(array $models)
 * @method setStocks(array $models)
 * @method setPriceDates(array $models)
 */
class Product extends Entity
{
    public const TABLE_PREFIX = 'p';
    public const TABLE_NAME = 'products';

    public const PARAM_SHOP_PRODUCT_ID = 'shop_product_id';
    public const PARAM_SHOP_PRODUCT_CODE = 'shop_product_code';
    public const PARAM_SOURCE_PRODUCT_ID = 'source_product_id';
    public const PARAM_BOOK_ID = 'book_id';
    public const PARAM_SHOP_ID = 'shop_id';
    public const PARAM_USER_ID = 'user_id';
    public const PARAM_TITLE = 'title';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    public const PARAM_SAME_PRODUCTS = 'same_products';
    public const PARAM_STOCKS = 'stocks';
    public const PARAM_PRICE_DATES = 'price_dates';

    public const PARAM_MIN_PRICE = 'min_price';
    public const PARAM_LAST_QTY = 'last_qty';

    public const PARAM_FLAGS = 'flags';
    public const PARAM_SOURCE_PRODUCT = 'source_product';
    public const PARAM_BOOK = 'book';
    public const PARAM_SHOP = 'shop';
    public const PARAM_PRODUCT_USER_DATA = 'product_user_data';

    public const FLAG_TO_SAVE_PRODUCT_USER_DATA = 'flag_to_save_product_user_data';
    public const FLAG_TO_SAVE_PRICE_DATES = 'flag_to_save_price_dates';
    public const FLAG_TO_SAVE_STOCKS = 'flag_to_save_stocks';
    public const FLAG_TO_CHANGE_ID = 'flag_to_change_id';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_SHOP_PRODUCT_ID => 'ID товара с магазина',
        self::PARAM_SHOP_PRODUCT_CODE => 'Код 1С',
        self::PARAM_BOOK_ID => 'ID книги',
        self::PARAM_SOURCE_PRODUCT_ID => 'ID источника товара',
        self::PARAM_TITLE => 'Название',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_ID,
        self::PARAM_SHOP_PRODUCT_ID,
        self::PARAM_DATE_UPDATED,
        self::PARAM_DATE_CREATED,
    ];

    protected const WHEN_CREATE_REQUIRED_PROPERTIES = [
        self::PARAM_SHOP_PRODUCT_ID,
        self::PARAM_TITLE,
    ];

    protected const RELATION_TO_ONE = [
        self::PARAM_SOURCE_PRODUCT => [
            'parent_id' => self::PARAM_SOURCE_PRODUCT_ID,
            'relation_entity' => SourceProduct::class,
            'relation_id' => Entity::PARAM_ID,
        ],
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
        ],
        self::PARAM_PRODUCT_USER_DATA => [
            'parent_id' => Entity::PARAM_ID,
            'relation_entity' => ProductUserData::class,
            'relation_id' => ProductUserData::PARAM_PRODUCT_ID,
            'relation_user_id' => ProductUserData::PARAM_USER_ID,
        ],
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
        ],
    ];

    protected int $shopProductId;
    protected ?string $shopProductCode;
    protected int $shopId;
    protected ?int $sourceProductId;
    protected ?int $bookId;
    protected string $title;
    protected DateTime $dateCreated;
    protected DateTime $dateUpdated;

    protected ?ProductUserData $productUserData = null;
    protected Shop $shop;
    protected ?SourceProduct $sourceProduct = null;
    protected ?Book $book = null;
    protected ?int $minPrice = null;
    protected ?int $lastQty = null;

    /** @var SameProduct[] */
    protected array $sameProducts = [];
    /** @var PriceDate[] */
    protected array $priceDates = [];
    /** @var Stock[] */
    protected array $stocks = [];

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
            $this->lastQty = end($stocks) ? end($stocks)->getQty() : null;
        }

        if ($this->getBook() && !$this->getBook()->getBookUserData()
            && $this->getProductUserData()) {
            $this->getProductUserData()->setIsArchive(true);
        }

        if ($this->getSourceProduct() && !$this->getSourceProduct()->getSourceProductUserData()
            && $this->getProductUserData()) {
            $this->getProductUserData()->setIsArchive(true);
        }
    }

    public function getUrl(): string|null
    {
        switch ($this->getShop()?->getType()) {
            case Config::TYPE_KNIGOFAN:
                return 'https://knigofan.ru/catalog/horus-heresy/primarkhi/929/';
            case Config::TYPE_WILDBERRIES:
                if ($this->getShopProductCode()) {
                    return 'https://www.wildberries.ru/catalog/' . $this->getShopProductCode() . '/detail.aspx?size=' . $this->getShopProductId();
                }

                return 'https://www.wildberries.ru/catalog/' . $this->getShopProductId() . '/detail.aspx';
            case Config::TYPE_OZON:
                return 'https://www.ozon.ru/product/' . $this->getShopProductId() . '/';
            case Config::TYPE_FFAN:
                return 'https://ffan.ru/catalog/product/' . $this->getShopProductId() . '/';
            case Config::TYPE_CHITAI_GOROD:
                return 'https://www.chitai-gorod.ru/product/-' . $this->getShopProductId();
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

    public function getUser(): User
    {
        return $this->getProductUserData()->getUser();
    }

    public function getAvailable(): bool
    {
        return $this->getProductUserData()->getAvailable();
    }

    public function getNotAvailableDateFrom(): ?DateTime
    {
        return $this->getProductUserData()->getNotAvailableDateFrom();
    }

    public function getAvailableDateFrom(): ?DateTime
    {
        return $this->getProductUserData()->getAvailableDateFrom();
    }

    public function getListenPriceValue(): ?int
    {
        return $this->getProductUserData()->getListenPriceValue();
    }

    public function getListenQtyValue(): ?int
    {
        return $this->getProductUserData()->getListenQtyValue();
    }

    public function getIsArchive(): bool
    {
        return $this->getProductUserData()->getIsArchive();
    }
}