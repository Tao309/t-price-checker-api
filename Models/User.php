<?php

namespace Models;

use DateTime;

/**
 * @method string getUsername()
 * @method string getAuthToken()
 * @method DateTime getDateCreated()
 */
class User extends Entity
{
    public const TABLE_PREFIX = 'u';
    public const TABLE_NAME = 'users';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_USERNAME => 'Логин',
        self::PARAM_AUTH_TOKEN => 'Токен авторизации',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    public const PARAM_USERNAME = 'username';
    public const PARAM_AUTH_TOKEN = 'auth_token';
    public const PARAM_DATE_CREATED = 'date_created';

    protected string $username;
    protected string $authToken;
    protected DateTime $dateCreated;
}