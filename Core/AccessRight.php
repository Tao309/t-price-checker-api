<?php

namespace Core;

use tResponse;

class AccessRight
{
    // tao309
    private const PRICE_CHECKER_ADMIN_AUTH_TOKEN = 'ksfu83jfregjewyrfwefewhfdhs3e';
    // a.sologub
    private const PRICE_CHECKER_SOLOGUB_AUTH_TOKEN = 'sfy830fn54y09w2rhw348932gyre0';

    // Перенеси в БД.
    private const PRICE_CHECKER_AUTH_TOKENS = [
        self::PRICE_CHECKER_ADMIN_AUTH_TOKEN,
        self::PRICE_CHECKER_SOLOGUB_AUTH_TOKEN,
    ];

    private static int $userId = 0;

    // tao309
    private const USER_ADMIN_ID = 2;
    // a.sologub
    private const USER_SOLOGUB_ID = 3;

    // Перенеси в БД.
    private const USER_IDS = [
        self::USER_ADMIN_ID,
        self::USER_SOLOGUB_ID,
    ];

    // Установка прав с токена авторизации.
    public static function applyUserAccess(string $userToken): void
    {
        self::$userId = 0;

        switch($userToken) {
            case self::PRICE_CHECKER_ADMIN_AUTH_TOKEN:
                self::$userId = self::USER_ADMIN_ID;
                break;
            case self::PRICE_CHECKER_SOLOGUB_AUTH_TOKEN:
                self::$userId = self::USER_SOLOGUB_ID;
                break;
            default:
                die(tResponse::MESSAGE_ACCESS_LIMITED);
        }
    }

    public static function isAdmin(): bool
    {
        return self::getCurrentUserid() === self::USER_ADMIN_ID;
    }

    public static function getCurrentUserid(): int
    {
        return self::$userId;
    }

    public static function getUserAuthTokens(): array
    {
        return self::PRICE_CHECKER_AUTH_TOKENS;
    }

    // Есть ли лимит для вывода связанных товаров.
    public static function isProductsViewedLimitAvailableForUser(): bool
    {
        if (self::isAdmin()) {
            return false;
        }

        return true;
    }

    // Лимит для связи товаров с корзины к плагину для юзера
    public static function getProductsViewedLimitForUser(): int
    {
        return 200;
    }

    // Доступно ли добавление источников книг для юзера.
    public static function isCreateBookAvailableForUser(): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        return false;
    }

    // Лимит для добавления источников книг для юзера.
    public static function getCreateBookLimitForUser(): int
    {
        return 10;
    }

    // Доступно ли добавление источников товаров для юзера.
    public static function isCreateSourceProductAvailableForUser(): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        return false;
    }

    // Лимит для добавления источников товаров для юзера.
    public static function getCreateSourceProductLimitForUser(): int
    {
        return 20;
    }
}