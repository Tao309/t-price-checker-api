<?php

namespace Models;

use Core\Config;

/**
 * @method string getProductId()
 * @method string getCode()
 * @method int|null getBookId()
 * @method string getShopType()
 * @method int getUserId()
 * @method int getTitle()
 * @method bool getAvailable()
 * @method string getNotAvailableDateFrom()
 * @method string getAvailableDateFrom()
 * @method int getListenPriceValue()
 * @method int getListenQtyValue()
 * @method string getReleaseDate()
 * @method string getDateCreated()
 * @method string getDateUpdated()
 * @method int getMinPrice()
 * @method int getLastQty()
 *
 * @method array getStocks()
 * @method array getPriceDates()
 * @method Book|null getBook()
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
    public const PARAM_DATE_CREATED = 'date_created';
    public const PARAM_DATE_UPDATED = 'date_updated';

    public const PARAM_MIN_PRICE = 'min_price';
    public const PARAM_LAST_QTY = 'last_qty';

    public const PARAM_BOOK = 'book';
    public const PARAM_SAME_PRODUCTS = 'same_products';

    public const FLAG_TO_SAVE_PRODUCT = 'flag_to_save_product';
    public const FLAG_TO_SAVE_PRICE_DATES = 'flag_to_save_price_dates';
    public const FLAG_TO_SAVE_STOCKS = 'flag_to_save_stocks';
    public const FLAG_TO_LINK_BOOK = 'flag_to_link_book';
    public const FLAG_TO_UNLINK_BOOK = 'flag_to_unlink_book';
    public const FLAG_TO_CHANGE_ID = 'flag_to_change_id';

    protected int $productId;
    protected ?string $code;
//    protected int $shopId;
    protected string $shopType;
//    protected int $userId;
    protected string $title;
    protected bool $available;
    protected string $notAvailableDateFrom;
    protected string $availableDateFrom;
    protected ?int $listenPriceValue;
    protected string $releaseDate;
    protected string $dateCreated;
    protected string $dateUpdated;

    protected ?Book $book = null;
    /** @var SameProduct[] */
    protected array $sameProducts = [];
    /** @var Stock[] */
    protected array $stocks = [];
    /** @var PriceDate[] */
    protected array $priceDates = [];
    protected ?int $minPrice = null;
    protected ?int $lastQty = null;

    public const RECORDABLE_PARAMS = [
        self::PARAM_PRODUCT_ID,
        self::PARAM_CODE,
        self::PARAM_SHOP_TYPE,
        self::PARAM_USER_ID,
        self::PARAM_TITLE,
        self::PARAM_LISTEN_PRICE_VALUE,
        self::PARAM_LISTEN_QTY_VALUE,
        self::PARAM_MIN_PRICE,
        self::PARAM_LAST_QTY,
    ];

    public const RECORDABLE_BOOLEAN_PARAMS = [
        self::PARAM_AVAILABLE,
    ];

    public const RECORDABLE_DATETIME_PARAMS = [
        self::PARAM_NOT_AVAILABLE_DATE_FROM,
        self::PARAM_AVAILABLE_DATE_FROM,
        self::PARAM_RELEASE_DATE,
        self::PARAM_DATE_CREATED,
        self::PARAM_DATE_UPDATED,
    ];

    public function __construct(array $data)
    {
        parent::__construct($data);

        if (!empty($data[Product::PARAM_PRICE_DATES])) {
            $price = [];

            $this->priceDates = array_map(function ($priceDateData) use (&$price) {
                $price[] = (int)$priceDateData[PriceDate::PARAM_PRICE] ?? 0;

                return new PriceDate([
                    PriceDate::PARAM_DATE => $this->formatDateToZeroTimezone($priceDateData[PriceDate::PARAM_DATE]),
                    PriceDate::PARAM_PRICE => (int)$priceDateData[PriceDate::PARAM_PRICE] ?? '---',
                ]);
            }, $data[self::PARAM_PRICE_DATES]);

            $this->minPrice = $price ? min($price) : null;
        }

        if (!empty($data[Product::PARAM_STOCKS])) {
            $this->stocks = array_map(function ($stockData) {
                return new Stock([
                    Stock::PARAM_DATE => $this->formatDateToZeroTimezone($stockData[Stock::PARAM_DATE]),
                    Stock::PARAM_QTY => (int)$stockData[Stock::PARAM_QTY] ?? '---',
                    Stock::PARAM_LOG => $stockData[Stock::PARAM_LOG],
                ]);
            }, $data[self::PARAM_STOCKS]);

            $this->lastQty = end($this->stocks)->getQty();
        }

        if (!empty($data['book.id'])) {
            $this->book = new Book([
                Entity::PARAM_ID => $data['book.id'],
                Book::PARAM_TITLE => $data['book.title'],
                Book::PARAM_AUTHOR => $data['book.author'],
                Book::PARAM_ISBN => $data['book.isbn'],
                Book::PARAM_PAGES => $data['book.pages'],
                Book::PARAM_CIRCULATION => $data['book.circulation'],
                Book::PARAM_SIZE => $data['book.size'],
                Book::PARAM_BINDING_TYPE_ID => $data['book.binding_type.id'],
                Book::PARAM_BINDING_TYPE_LABEL => $data['book.binding_type.label'],
                Book::PARAM_RELEASE_DATE => $data['book.release_date'],
                Book::PARAM_PUBLISH_YEAR => $data['book.publish_year'],
                Book::PARAM_DATE_CREATED => $data['book.date_created'],
            ]);
        }

        if (!empty($data[self::PARAM_SAME_PRODUCTS])) {
            $this->sameProducts = array_map(function ($sameProductData) {
                return new SameProduct($sameProductData);
            }, $data[self::PARAM_SAME_PRODUCTS]);
        }
    }

//    public function toArray(): array
//    {
//        $m = parent::toArray();
//        unset($m[self::PARAM_USER_ID], $m[self::PARAM_SHOP_ID]);

//        if ($this->getPriceDates()) {
//            $m[self::PARAM_PRICE_DATES] = array_map(function ($priceDate) {
//                return $priceDate->toArray();
//            }, $this->getPriceDates());
//        }

//        if ($this->getStocks()) {
//            $m[self::PARAM_STOCKS] = array_map(function ($stock) {
//                return $stock->toArray();
//            }, $this->getStocks());
//        }

//        if ($this->getBook()) {
//            $m[self::PARAM_BOOK] =  $this->getBook()->toArray();
//        }

//        if ($this->getSameProducts()) {
//            $m[self::PARAM_SAME_PRODUCTS] = array_map(function ($sameProduct) {
//                return $sameProduct->toArray();
//            }, $this->getSameProducts());
//        }

//        return $m;
//    }

    public function getUrl(): string|null
    {
        switch ($this->getShopType()) {
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
}