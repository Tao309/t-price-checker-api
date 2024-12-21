<?php

namespace Models;

use DateTime;

/**
 * @method int getUserId()
 * @method string getAuthToken()
 * @method DateTime getDateUpdated()
 * @method DateTime getDateCreated()
 */
class AuthToken extends Entity
{
    public const TABLE_PREFIX = 'at';
    public const TABLE_NAME = 'auth_token';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_AUTH_TOKEN => 'Токен авторизации',
        self::PARAM_DATE_UPDATED => 'Дата обновления',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [
        self::PARAM_AUTH_TOKEN,
    ];

    public const PARAM_USER_ID = 'user_id';
    public const PARAM_AUTH_TOKEN = 'auth_token';
    public const PARAM_DATE_UPDATED = 'date_updated';
    public const PARAM_DATE_CREATED = 'date_created';

    protected string $userId;
    protected string $authToken;
    protected DateTime $dateUpdated;
    protected DateTime $dateCreated;
}