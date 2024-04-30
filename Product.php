<?php

use Models\Book;
use Models\Entity;
use Models\SameProduct;

class Product
{
    public const PARAM_ID = 'id';
    public const PARAM_PRODUCT_ID = 'product_id';
    public const PARAM_BOOK_ID = 'book_id';
    public const PARAM_SHOP_ID = 'shop_id';
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

    public const PARAM_BOOK = 'book';
    public const PARAM_SAME_PRODUCTS = 'same_products';

    public const FLAG_TO_SAVE_PRODUCT = 'flag_to_save_product';
    public const FLAG_TO_SAVE_PRICE_DATES = 'flag_to_save_price_dates';
    public const FLAG_TO_SAVE_STOCKS = 'flag_to_save_stocks';
    public const FLAG_TO_LINK_BOOK = 'flag_to_link_book';
    public const FLAG_TO_UNLINK_BOOK = 'flag_to_unlink_book';

    private int $id;
    private int $productId;
    private int $shopId;
    private string $shopType;
    private int $userId;
    private string $title;
    private bool $available;
    private string $notAvailableDateFrom;
    private string $availableDateFrom;
    private ?int $listenPriceValue;
    private string $releaseDate;
    private ?array $stocks;
    private ?array $priceDates;
    private string $dateCreated;
    private string $dateUpdated;

    private ?Book $book = null;
    /** @var SameProduct[] */
    private array $sameProducts = [];

    public const RECORDABLE_PARAMS = [
        self::PARAM_PRODUCT_ID,
        'shop_type',
        self::PARAM_USER_ID,
        self::PARAM_TITLE,
        self::PARAM_LISTEN_PRICE_VALUE,
        self::PARAM_LISTEN_QTY_VALUE,
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
        foreach ($data as $param => $value) {
            $camelCaseParam = $this->getCamelCaseParam($param);

            if ($camelCaseParam === self::PARAM_ID) {
                $this->$camelCaseParam = $value;
            }

            if (in_array($param, self::RECORDABLE_PARAMS)) {
                $this->$camelCaseParam = $value;
                continue;
            }

            if (in_array($param, self::RECORDABLE_BOOLEAN_PARAMS)) {
                $this->$camelCaseParam = (bool)$value;
                continue;
            }

            if (!empty($value) && in_array($param, self::RECORDABLE_DATETIME_PARAMS)) {
                $this->$camelCaseParam = $this->formatDateToZeroTimezone($value);
                continue;
            }
        }

        if (isset($data[Product::PARAM_PRICE_DATES])) {
            foreach ($data[Product::PARAM_PRICE_DATES] as $priceDateData) {
                $this->priceDates[] = [
                    'date' => $this->formatDateToZeroTimezone($priceDateData['date']),
                    'price' => (int) $priceDateData['price'] ?? '---',
                ];
            }
        }

        if (isset($data[Product::PARAM_STOCKS])) {
            foreach ($data[Product::PARAM_STOCKS] as $stockData) {
                $this->stocks[] =  [
                    'date' => $this->formatDateToZeroTimezone($stockData['date']),
                    'qty' => (int) $stockData['qty'] ?? '---',
                    'log' => $stockData['log'],
                ];
            }
        }

        if (isset($data['book.id'])) {
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

        if (isset($data[self::PARAM_SAME_PRODUCTS])) {
            foreach ($data[self::PARAM_SAME_PRODUCTS] as $sameProductData) {
                $this->sameProducts[] = new SameProduct($sameProductData);
            }
        }
    }

    public function toArray(): array
    {
        $vars = get_object_vars($this);

        $m = [];
        foreach ($vars as $key => $value ) {
            $m[$this->getSnakeCaseParam($key)] = $value;
        }

        unset($m['user_id'], $m['shop_id'], $m['book']);

        if ($this->book) {
            $m['book'] =  $this->book->toArray();
        }

        if ($this->sameProducts) {
            $m[self::PARAM_SAME_PRODUCTS] = [];

            foreach ($this->sameProducts as $sameProduct) {
                $m[self::PARAM_SAME_PRODUCTS][] = $sameProduct->toArray();
            }
        }

        return $m;
    }

    private function getCamelCaseParam(string $snakeCaseParam): string
    {
        $snakeCaseParam = mb_convert_case($snakeCaseParam, MB_CASE_TITLE, "UTF-8");

        return lcfirst(str_replace('_', '', $snakeCaseParam));
    }

    private function getSnakeCaseParam(string $param): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $param));
    }

    private function formatDateToZeroTimezone(string $dateString): string
    {
        $date = new DateTime($dateString);
        $date->modify('+3 hours');

        return $date->format('Y-m-d H:i:s');
    }

}