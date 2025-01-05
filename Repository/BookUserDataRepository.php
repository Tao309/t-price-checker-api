<?php

namespace Repository;

use Models\BookUserData;

/**
 * @method BookUserData find($userId, $bookId)
 */
class BookUserDataRepository extends Repository
{
    protected string $entityModel = BookUserData::class;
}