<?php

namespace AccessRights;

interface UserAccessInterface
{
    public function getProductLimit(): int;

    public function getBookLimit(): int;

    public function getSourceProductLimit(): int;
}
