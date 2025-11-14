<?php

namespace AccessRights;

use Core\ArrayHandler;
use Core\tResponse;
use Exception\NoRightsException;
use Models\Entity;
use Models\User;
use Models\UserRole;
use Repository\AuthTokenRepository;

class AccessHandler
{
    public const VALUE_DEFAULT_PRODUCT_LIMIT = 50;

    private static int $userId = 0;
    private static string|null $userRole = null;

    static array $rights = [];

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
            'add_publishing_house',
            'add_publishing_brand',
            'add_series',
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

        $user = $authTokenRepository->getUserByAuthToken($userToken);

        if (!$user) {
            throw new NoRightsException('User is not found by token');
        }

        if (!$user->getIsActive()) {
            throw new NoRightsException(sprintf(
                'User %s is not active',
                $user->getUsername()
            ));
        }

        self::$userRole = $user->getUserRole()->getCode();
        self::$userId = $user->getId();

//        $userData = $authTokenRepository->getUserDataByAuthToken($userToken);
//
//        if (!$userData
//            || empty(ArrayHandler::getValueAsInt(User::PARAM_ID, $userData))
//            || empty(ArrayHandler::getValueAsString(User::PARAM_USERNAME, $userData))
//        ) {
//            throw new NoRightsException('User is not found by token');
//        }
//
//        if (!ArrayHandler::getValueAsBool(User::PARAM_IS_ACTIVE, $userData)) {
//            throw new NoRightsException(sprintf(
//                'User %s is not active',
//                ArrayHandler::getValueAsString(User::PARAM_USERNAME, $userData)
//            ));
//        }
//
//        self::$userRole = match (ArrayHandler::getValueAsString(User::PARAM_USERNAME, $userData)) {
//            self::USER_NAME_ADMIN,
//            self::USER_NAME_TAO309 => self::USER_ADMIN_ROLE,
//            self::USER_NAME_SOLOGUB => self::USER_USER_ROLE,
//            default => throw new(tResponse::MESSAGE_ACCESS_LIMITED),
//        };
//
//        self::$userId = $userData[Entity::PARAM_ID];
    }

    public static function isAdmin(): bool
    {
        return self::$userRole === UserRole::USER_ADMIN_ROLE;
    }

    public static function getCurrentUserId(): int
    {
        if (empty(self::$userId)) {
            throw new NoRightsException('Current user is not authenticated');
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