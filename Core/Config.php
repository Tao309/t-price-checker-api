<?php

namespace Core;

use Exception\ResponseException;
use Models\Entity;
use Models\SourceProductType;
use Models\Shop;
use QueryPdo;
use tResponse;

class Config
{
    const TYPE_OZON = 'ozon';//www.ozon.ru
    const TYPE_WILDBERRIES = 'wildberries';//www.wildberries.ru
    const TYPE_CHITAI_GOROD = 'chitai-gorod';//www.chitai-gorod.ru
    const TYPE_FFAN = 'ffan';//ffan.ru
    const TYPE_KNIGOFAN = 'knigofan';//knigofan.ru

    const AVAILABLES_TYPES = [
        self::TYPE_OZON,
        self::TYPE_WILDBERRIES,
        self::TYPE_CHITAI_GOROD,
        self::TYPE_FFAN,
        self::TYPE_KNIGOFAN,
    ];

    private static ?int $currentShopId = null;
    private static ?string $currentShopType = null;
    private static int $userId = 2;// tao309.
    private static ?array $shopTypes = null;
    private static ?array $sourceProductTypes = null;

    public static function getCurrentUserid(): int
    {
        return self::$userId;
    }

    public static function getCurrentShopType(): string
    {
        if (is_null(self::$currentShopType)) {
            throw new \Exception('Shop type is empty.');
        }

        return self::$currentShopType;
    }

    public static function getCurrentShopId(): string
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

    public static function checkHeaders(): void
    {
        // Вынести в конфиг, чтобы находить user_id
        $headers = getallheaders();

        if (empty($headers['x-requested-with']) && $headers['x-requested-with'] !== 'tRequest') {
            die(tResponse::MESSAGE_ACCESS_LIMITED);
        }

        $myPriceCheckerId = 'ksfu83jfregjewyrfwefewhfdhs3e'; // tao309

        // checkAuthToken from header
        if (empty($headers['t-price-checker-id']) && $headers['t-price-checker-id'] !== $myPriceCheckerId) {
            die(tResponse::MESSAGE_ACCESS_LIMITED);
        }
    }

    public static function initShopType(string $shopType): void
    {
        if (!in_array($shopType, self::AVAILABLES_TYPES)) {
            throw new \Exception('Type ' . $shopType . ' is not exists.');
        }

        self::$currentShopType = $shopType;

        if (is_null(self::$shopTypes)) {
            self::$shopTypes = [];

            $cacheId = 'shops';
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

        $cacheId = 'source_product_types';
        if (!Cache::isCacheExists($cacheId)) {
            $query = (new QueryPdo())->select('*')->from(SourceProductType::TABLE_NAME);
            Cache::saveCache($cacheId, $query->fetchAll());
        }

        foreach (Cache::getCache($cacheId, Cache::TYPE_ARRAY) as $row) {
            self::$sourceProductTypes[$row[Entity::PARAM_ID]] = $row;
        }
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

    public static function getDateTime(string $dateTime = null): \DateTime
    {
        $timezone = new \DateTimeZone('Europe/Moscow');

        $date = new \DateTime($dateTime);
        $date->setTimezone($timezone);
        $date->modify('-3 hours');

        return $date;
    }
}