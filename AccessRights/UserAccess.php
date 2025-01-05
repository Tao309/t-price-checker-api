<?php

namespace AccessRights;

use Core\Config;

class UserAccess extends AccessHandler implements UserAccessInterface, AdminAccessInterface
{
    public function getProductCreate(): bool
    {
        return true;
    }

    public function getProductUpdate(): bool
    {
        return false;
    }

    public function getProductLimitEnabled(): bool
    {
        return true;
    }

    public function getProductLimit(): int
    {
        return AccessHandler::VALUE_DEFAULT_PRODUCT_LIMIT;
    }

    public function getBookUpdate(): bool
    {
        return false;
    }

    public function getBookCreate(): bool
    {
        return false;
    }

    public function getBookLimit(): int
    {
        return 10;
    }

    public function getSourceProductEnabled(): bool
    {
        return true;
    }

    public function getSourceProductCreate(): bool
    {
        return true;
    }

    public function getSourceProductUpdate(): bool
    {
        return false;
    }

    public function getSourceProductLimit(): int
    {
        return 20;
    }

    public function getShopList(): array
    {
        return [
            Config::TYPE_WILDBERRIES,
            Config::TYPE_OZON,
        ];
    }
}
