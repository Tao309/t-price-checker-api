<?php

namespace Core;

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
            $query = (new QueryPdo())->select('*')->from('shops');

            foreach ($query->fetchAll() as $row) {
                self::$shopTypes[$row['type']] = $row['id'];
            }
        }

        if (!isset(self::$shopTypes[$shopType])) {
            throw new \Exception('Not found shop type ' . $shopType);
        }

        self::$currentShopId = self::$shopTypes[$shopType];
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

}