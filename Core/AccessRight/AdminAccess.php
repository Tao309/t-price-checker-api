<?php

namespace Core\AccessRight;

use Core\Config;
use Models\Shop;

class AdminAccess extends AccessRight implements AdminAccessInterface
{
    public function getProductCreate(): bool
    {
        return true;
    }

    public function getProductUpdate(): bool
    {
        return true;
    }

    public function getProductLimitEnabled(): bool
    {
        return false;
    }

    public function getBookUpdate(): bool
    {
        return true;
    }

    public function getBookCreate(): bool
    {
        return true;
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

    public function getShopList(): array
    {
        return [
            Config::TYPE_OZON,
            Config::TYPE_WILDBERRIES,
            Config::TYPE_CHITAI_GOROD,
            Config::TYPE_FFAN,
            Config::TYPE_KNIGOFAN,
        ];
    }
}
