<?php

namespace Core\AccessRight;

interface AdminAccessInterface
{
    public function getProductCreate(): bool;

    public function getProductSave(): bool;

    public function getProductLimitEnabled(): bool;

    public function getBookSave(): bool;

    public function getBookCreate(): bool;

    public function getSourceProductEnabled(): bool;

    public function getSourceProductCreate(): bool;
}
