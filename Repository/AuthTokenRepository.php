<?php

namespace Repository;

use Models\AuthToken;
use Models\Entity;
use Models\User;
use QueryPdo;

class AuthTokenRepository extends Repository
{
    protected string $entityModel = AuthToken::class;

    public function getUserDataByAuthToken(string $authToken): array|null
    {
        $query = (new QueryPdo())
            ->select()
            ->from([
                'at' => AuthToken::TABLE_NAME
            ])
            ->rightJoin(
                ['u' => User::TABLE_NAME],
                sprintf(
                    'at.%s = u.%s',
                    AuthToken::PARAM_USER_ID,
                    Entity::PARAM_ID
                ),
                [
                    'u.' . User::PARAM_ID,
                    'u.' . User::PARAM_IS_ACTIVE,
                    'u.' . User::PARAM_USERNAME
                ]
            )
            ->where(AuthToken::PARAM_AUTH_TOKEN, $authToken);

        return $query->fetch();
    }
}