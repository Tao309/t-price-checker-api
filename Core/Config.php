<?php

namespace Core;

use AccessRights\AccessHandler;
use AccessRights\AdminAccess;
use AccessRights\UserAccess;
use Exception\NoRightsException;
use Exception\ResponseException;
use Models\BindingType;
use Models\BookSeries;
use Models\Entity;
use Models\PublishingBrand;
use Models\PublishingHouse;
use Models\Shop;
use Models\SourceProductType;
use Models\UserRole;
use Query\QueryPdo;

class Config
{
    public const TYPE_OZON = 'ozon';
    public const TYPE_WILDBERRIES = 'wildberries';
    public const TYPE_CHITAI_GOROD = 'chitai-gorod';
    public const TYPE_FFAN = 'ffan';
    public const TYPE_KNIGOFAN = 'knigofan';

    private const AVAILABLE_TYPES = [
        self::TYPE_OZON,
        self::TYPE_WILDBERRIES,
        self::TYPE_CHITAI_GOROD,
        self::TYPE_FFAN,
        self::TYPE_KNIGOFAN,
    ];

    private static ?int $currentShopId = null;
    private static ?string $currentShopType = null;
    private static ?array $shopTypes = null;
    private static ?array $sourceProductTypes = null;
    private static ?array $bookBindingTypes = null;
    private static ?array $bookPublishingHouses = null;
    private static ?array $bookPublishingBrands = null;
    private static ?array $bookSeries = null;

    public static function getCurrentUserid(): int
    {
        return AccessHandler::getCurrentUserId();
    }

    public static function getCurrentShopType(): string
    {
        if (is_null(self::$currentShopType)) {
            throw new \Exception('Shop type is empty.');
        }

        return self::$currentShopType;
    }

    public static function getCurrentShopId(): int
    {
        if (is_null(self::$currentShopId)) {
            throw new \Exception('Shop id is empty.');
        }

        return self::$currentShopId;
    }

    public static function getShopIdByType(string $shopType)
    {
        if (!isset(self::$shopTypes[$shopType])) {
            throw new \Exception('Not found id for shop type ' . $shopType);
        }

        return self::$shopTypes[$shopType];
    }

    public static function checkHeadersAndApplyAccess(): void
    {
        $headers = getallheaders();

        $requestedWith = $headers['x-requested-with'] ?? null;
        $priceCheckerId = $headers['t-price-checker-id']  ?? null;

        if (empty($priceCheckerId)) {
            $requestedWith = $_SERVER['x-requested-with'] ?? null;
            $priceCheckerId = $_SERVER['t-price-checker-id'] ?? null;
        }

        if (empty($requestedWith) || $requestedWith !== 'tRequest') {
            die(tResponse::MESSAGE_ACCESS_LIMITED);
        }

        // checkAuthToken from header
        if (empty($priceCheckerId)) {
            die(tResponse::MESSAGE_ACCESS_LIMITED);
        }

        AccessHandler::applyUserAccess($priceCheckerId);

        match (AccessHandler::getCurrentUserRole()) {
            UserRole::USER_ADMIN_ROLE => new AdminAccess(),
            UserRole::USER_USER_ROLE => new UserAccess(),
            default => throw new NoRightsException('Role for user is not found')
        };

        if (!in_array(Config::getCurrentShopType(), AccessHandler::getAccessConfig('shop.list', []))) {
            throw new NoRightsException(
                sprintf(
                    'Current shop %s is not allowed.',
                    Config::getCurrentShopType()
                )
            );
        }
    }

    public static function initShopType(string $shopType): void
    {
        if (!in_array($shopType, self::AVAILABLE_TYPES)) {
            throw new \Exception('Type ' . $shopType . ' is not exists.');
        }

        self::$currentShopType = $shopType;

        if (is_null(self::$shopTypes)) {
            self::$shopTypes = [];

            $cacheId = Shop::TABLE_NAME;
            if (!Cache::isCacheExists($cacheId)) {
                $query = (new QueryPdo())->select('*')->from(Shop::TABLE_NAME);
                Cache::saveCache($cacheId, $query->fetchAll());
            }

            foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
                self::$shopTypes[$row[Shop::PARAM_TYPE]] = $row[Shop::PARAM_ID];
            }
        }

        if (!isset(self::$shopTypes[$shopType])) {
            throw new \Exception('Not found shop type ' . $shopType);
        }

        self::$currentShopId = self::$shopTypes[$shopType];
    }

    public static function initSourceProductTypes(): void
    {
        if (!is_null(self::$sourceProductTypes)) {
            return;
        }

        self::$sourceProductTypes = [];

        $cacheId = SourceProductType::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            $query = (new QueryPdo())->select('*')->from(SourceProductType::TABLE_NAME);
            Cache::saveCache($cacheId, $query->fetchAll());
        }

        foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
            self::$sourceProductTypes[$row[Entity::PARAM_ID]] = $row;
        }
    }

    public static function getSourceProductTypes(): array
    {
        self::initSourceProductTypes();

        $types = self::$sourceProductTypes;

        usort($types, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $types;
    }

    public static function getSourceProductTypeIdByCode(string $productTypeCode): int
    {
        self::initSourceProductTypes();

        $foundKey = array_search($productTypeCode, array_column(self::$sourceProductTypes, SourceProductType::PARAM_CODE));

        if (!$foundKey) {
            throw new ResponseException('Product Type by code ' . $productTypeCode . ' is not found');
        }

        return self::$sourceProductTypes[$foundKey][Entity::PARAM_ID];
    }

    public static function initBookBindingTypes(): void
    {
        if (!is_null(self::$bookBindingTypes)) {
            return;
        }

        self::$bookBindingTypes = [];

        $cacheId = BindingType::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            $query = (new QueryPdo())->select('*')->from(BindingType::TABLE_NAME);
            Cache::saveCache($cacheId, $query->fetchAll());
        }

        foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
            self::$bookBindingTypes[$row[Entity::PARAM_ID]] = $row;
        }
    }

    public static function initPublishingHouses(): void
    {
        if (!is_null(self::$bookPublishingHouses)) {
            return;
        }

        self::$bookPublishingHouses = [];

        $cacheId = PublishingHouse::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            $query = (new QueryPdo())->select('*')->from(PublishingHouse::TABLE_NAME);
            Cache::saveCache($cacheId, $query->fetchAll());
        }

        foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
            self::$bookPublishingHouses[$row[Entity::PARAM_ID]] = $row;
        }
    }

    public static function clearPublishingHouses(): void
    {
        $cacheId = PublishingHouse::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            return;
        }

        Cache::removeCache($cacheId);
    }

    public static function initPublishingBrands(): void
    {
        if (!is_null(self::$bookPublishingBrands)) {
            return;
        }

        self::$bookPublishingBrands = [];

        $cacheId = PublishingBrand::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            $query = (new QueryPdo())->select('*')->from(PublishingBrand::TABLE_NAME);
            Cache::saveCache($cacheId, $query->fetchAll());
        }

        foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
            self::$bookPublishingBrands[$row[Entity::PARAM_ID]] = $row;
        }
    }

    public static function clearPublishingBrands(): void
    {
        $cacheId = PublishingBrand::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            return;
        }

        Cache::removeCache($cacheId);
    }

    public static function initBookSeries(): void
    {
        if (!is_null(self::$bookSeries)) {
            return;
        }

        self::$bookSeries = [];

        $cacheId = BookSeries::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            $query = (new QueryPdo())->select('*')->from(BookSeries::TABLE_NAME);
            Cache::saveCache($cacheId, $query->fetchAll());
        }

        foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
            self::$bookSeries[$row[Entity::PARAM_ID]] = $row;
        }
    }

    public static function clearBookSeries(): void
    {
        $cacheId = BookSeries::TABLE_NAME;
        if (!Cache::isCacheExists($cacheId)) {
            return;
        }

        Cache::removeCache($cacheId);
    }

    public static function getBookBindingTypes(): array
    {
        self::initBookBindingTypes();

        return self::$bookBindingTypes;
    }

    // @todo сделать один метод на получение и инит по всем типам
    public static function getPublishingHouses(): array
    {
        self::initPublishingHouses();

        $houses = self::$bookPublishingHouses;

        usort($houses, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $houses;
    }

    public static function getPublishingBrands(): array
    {
        self::initPublishingBrands();

        $bookPublishingBrands = self::$bookPublishingBrands;

        usort($bookPublishingBrands, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $bookPublishingBrands;
    }

    public static function getBookSeries(): array
    {
        self::initBookSeries();

        $bookSeries = self::$bookSeries;

        usort($bookSeries, function($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $bookSeries;
    }

    public static function isWildberriesShopType(): bool
    {
        return self::getCurrentShopType() === self::TYPE_WILDBERRIES;
    }

    public static function isOzonShopType(): bool
    {
        return self::getCurrentShopType() === self::TYPE_OZON;
    }

    public static function isChitaiGorodShopType(): bool
    {
        return self::getCurrentShopType() === self::TYPE_CHITAI_GOROD;
    }

    public static function getWildberriesUrl(): string
    {
        return getenv('URL_WB') ?? 'https://www.wildberries.ru';
    }

    public static function getOzonUrl(): string
    {
        return getenv('URL_OZON') ?? 'https://www.ozon.ru';
    }

    public static function getChitaiGorodUrl(): string
    {
        return getenv('URL_CHITAI_GOROD') ?? 'https://www.chitai-gorod.ru';
    }

    public static function getFfanUrl(): string
    {
        return getenv('URL_FFAN') ?? 'https://ffan.ru';
    }

    public static function getKnigofanUrl(): string
    {
        return getenv('URL_KNIGOFAN') ?? 'https://knigofan.ru';
    }

    public static function getDateTime(string $dateTime = null): \DateTime
    {
        $timezone = new \DateTimeZone('Europe/Moscow');

        $date = new \DateTime($dateTime);
        $date->setTimezone($timezone);
        $date->modify('-3 hours');

        return $date;
    }
}