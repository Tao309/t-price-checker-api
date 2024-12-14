<?php

namespace Core\AccessRight;

class AdminAccess extends AccessRight implements AdminAccessInterface
{
    public function getProductCreate(): bool
    {
        return true;
    }

    public function getProductSave(): bool
    {
        return true;
    }

    public function getProductLimitEnabled(): bool
    {
        return false;
    }

    public function getBookSave(): bool
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
}
