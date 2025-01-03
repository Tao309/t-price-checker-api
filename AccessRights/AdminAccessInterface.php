<?php

namespace AccessRights;

interface AdminAccessInterface
{
    public function getProductCreate(): bool;

    public function getProductUpdate(): bool;

    public function getProductLimitEnabled(): bool;

    public function getBookUpdate(): bool;

    public function getBookCreate(): bool;

    public function getSourceProductEnabled(): bool;

    public function getSourceProductCreate(): bool;

    public function getSourceProductUpdate(): bool;

    public function getShopList(): array;
}
