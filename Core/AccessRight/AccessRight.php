<?php

namespace Core\AccessRight;

use Exception\ResponseException;
use Models\Entity;
use tResponse;

class AccessRight
{
    public const USER_ADMIN_ROLE = 'admin';
    public const USER_USER_ROLE = 'user';

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

    private static string|null $userRole = null;

    // tao309
    private const USER_ADMIN_ID = 2;
    // a.sologub
    private const USER_SOLOGUB_ID = 3;

    // Перенеси в БД.
    private const USER_IDS = [
        self::USER_ADMIN_ID,
        self::USER_SOLOGUB_ID,
    ];

    static $rights = [];

    /**
     * Список для обработки в ролях пользователей.
     * Роль product.save ищет метод getProductSave
     * Роль source_product.enabled ищет метод getSourceProductEnabled
     */
    private const ACCESS_LIST = [
        'product' => [
            'save',
            'create',
            'limit_enabled',
            'limit',
        ],
        'book' => [
            'save',
            'create',
            'limit',
        ],
        'source_product' => [
            'enabled',
            'create',
            'limit',
        ]
    ];

    public function __construct()
    {
        self::$rights = $this->getAccessData();
    }

    // Установка прав с токена авторизации.
    public static function applyUserAccess(string $userToken): void
    {
        self::$userId = 0;

        switch($userToken) {
            case self::PRICE_CHECKER_ADMIN_AUTH_TOKEN:
                self::$userId = self::USER_ADMIN_ID;
                self::$userRole = self::USER_ADMIN_ROLE;
                break;
            case self::PRICE_CHECKER_SOLOGUB_AUTH_TOKEN:
                self::$userId = self::USER_SOLOGUB_ID;
                self::$userRole = self::USER_USER_ROLE;
                break;
            default:
                die(tResponse::MESSAGE_ACCESS_LIMITED);
        }
    }

    public static function isAdmin(): bool
    {
        return self::getCurrentUserId() === self::USER_ADMIN_ID;
    }

    public static function getCurrentUserId(): int
    {
        if (empty(self::$userId)) {
            throw new ResponseException('Current user is not authenticated');
        }

        return self::$userId;
    }

    public static function getCurrentUserRole(): string
    {
        return self::$userRole;
    }

    public static function getUserAuthTokens(): array
    {
        return self::PRICE_CHECKER_AUTH_TOKENS;
    }

    public static function hasAccess(string $path, $defaultValue = null): bool
    {
        return true;

        $path = explode('.', $path);
        $rightsConfig = self::getRights();
        $currentConfig = null;

        foreach ($path as $part) {
            $part = trim($part);

            if (!isset($rightsConfig[$part])) {
                return $defaultValue;
            }

            $currentConfig = $rightsConfig[$part];
        }

        return $currentConfig ?? $defaultValue;
    }

    public static function getRights(): array
    {
        return self::$rights;
    }

    private function getAccessData(): array
    {
        $result = [];

        foreach (self::ACCESS_LIST as $type => $actionList) {
            $result[$type] = [];

            foreach ($actionList as $action) {
                $methodName = Entity::toCamelCase('get_' . $type . '_' . $action);

                // в статику перекинуть?
                $result[$type][$action] = method_exists($this, $methodName) ? $this->$methodName() : null;
            }
        }

        return $result;
    }
}