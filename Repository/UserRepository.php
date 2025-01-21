<?php

namespace Repository;

use Models\User;

/**
 * @method User find(int $userId)
 * @method User[] findByParams(array $params, array $filters = [])
 */
class UserRepository extends Repository
{
    protected string $entityModel = User::class;
}