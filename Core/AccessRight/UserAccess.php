<?php

namespace Core\AccessRight;

class UserAccess extends AccessRight implements UserAccessInterface, AdminAccessInterface
{
    public function getProductCreate(): bool
    {
        return false;
    }

    public function getProductSave(): bool
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

    public function getBookSave(): bool
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
        return false;
    }

    public function getSourceProductCreate(): bool
    {
        return false;
    }

    public function getSourceProductLimit(): int
    {
        return 20;
    }
}
