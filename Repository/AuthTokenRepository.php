<?php

namespace Repository;

use Exception\ResponseException;
use Models\AuthToken;
use Models\Entity;
use Models\User;
use Query\QueryPdo;

class AuthTokenRepository extends Repository
{
    protected string $entityModel = AuthToken::class;

    public function getUserByAuthToken(string $authToken): User|null
    {
        $userRepository = new UserRepository();

        $userId = (new QueryPdo())
            ->select([
                Entity::PARAM_ID
            ])
            ->from(AuthToken::TABLE_NAME)
            ->where(AuthToken::PARAM_AUTH_TOKEN, $authToken)
            ->fetchColumn();

        if (!$userId) {
            throw new ResponseException('Not found user by token');
        }

        return $userRepository->find($userId);
    }

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