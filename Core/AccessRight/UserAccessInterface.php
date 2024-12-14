<?php

namespace Core\AccessRight;

interface UserAccessInterface
{
    public function getProductLimit(): int;

    public function getBookLimit(): int;

    public function getSourceProductLimit(): int;
}
