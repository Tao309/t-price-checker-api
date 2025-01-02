<?php

namespace Core\AccessRight;

use Core\Config;

class UserAccess extends AccessRight implements UserAccessInterface, AdminAccessInterface
{
    public function getProductCreate(): bool
    {
        return false;
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
        return 200;
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
        return true;
    }

    public function getSourceProductLimit(): int
    {
        return 20;
    }

    public function getShopList(): array
    {
        return [
            Config::TYPE_WILDBERRIES,
        ];
    }
}
