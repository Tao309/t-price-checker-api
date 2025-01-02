<?php

namespace Core\AccessRight;

use Exception\NoRightsException;
use Exception\ResponseException;
use Models\Entity;
use Models\User;
use Repository\AuthTokenRepository;
use tResponse;

class AccessRight
{
    public const USER_ADMIN_ROLE = 'admin';
    public const USER_USER_ROLE = 'user';

    private static int $userId = 0;

    private static string|null $userRole = null;

    private const USER_NAME_ADMIN = 'admin';
    private const USER_NAME_TAO309 = 'tao309';
    private const USER_NAME_SOLOGUB = 'a.sologub';

    static $rights = [];

    /**
     * Список для обработки в ролях пользователей.
     * Роль product.save ищет метод getProductSave
     * Роль source_product.enabled ищет метод getSourceProductEnabled
     */
    private const ACCESS_LIST = [
        'product' => [
            'update',
            'create',
            'limit_enabled',
            'limit',
        ],
        'book' => [
            'update',
            'create',
            'limit',
        ],
        'source_product' => [
            'enabled',
            'create',
            'update',
            'limit',
        ],
        'shop' => [
            'list'
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

        $authTokenRepository = new AuthTokenRepository();

        $userData = $authTokenRepository->getUserDataByAuthToken($userToken);

        if (!$userData || empty($userData[Entity::PARAM_ID]) || empty($userData[User::PARAM_USERNAME])) {
            throw new NoRightsException('User is not found by token');
        }

        self::$userRole = match ($userData[User::PARAM_USERNAME]) {
            self::USER_NAME_ADMIN,
            self::USER_NAME_TAO309 => self::USER_ADMIN_ROLE,
            self::USER_NAME_SOLOGUB => self::USER_USER_ROLE,
            default => throw new(tResponse::MESSAGE_ACCESS_LIMITED),
        };

        self::$userId = $userData[Entity::PARAM_ID];
    }

    public static function isAdmin(): bool
    {
        return self::$userRole === self::USER_ADMIN_ROLE;
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

    public static function checkAccess(string $rightPath): void
    {
        $rightsConfig = self::getAccessConfig($rightPath, false);

        if (!$rightsConfig) {
            throw new NoRightsException('Access rights not found for ' . $rightPath);
        }
    }

    public static function getAccessConfig(string $rightPath, $defaultValue = null)
    {
        $path = explode('.', $rightPath);
        $rightsConfig = self::getRights();

        foreach ($path as $part) {
            $part = trim($part);

            if (!isset($rightsConfig[$part])) {
                return $defaultValue;
            }

            $rightsConfig = $rightsConfig[$part];
        }

        return $rightsConfig;
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
                $result[$type][$action] = method_exists($this, $methodName) ? $this->$methodName() : null;
            }
        }

        return $result;
    }
}