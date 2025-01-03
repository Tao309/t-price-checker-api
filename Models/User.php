<?php

namespace Models;

use DateTime;

/**
 * @method string getUsername()
 * @method bool getIsActive()
 * @method DateTime getDateCreated()
 */
class User extends Entity
{
    public const TABLE_PREFIX = 'u';
    public const TABLE_NAME = 'users';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_USERNAME => 'Логин',
        self::PARAM_IS_ACTIVE => 'Активен',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [

    ];

    public const PARAM_USERNAME = 'username';
    public const PARAM_IS_ACTIVE = 'is_active';
    public const PARAM_DATE_CREATED = 'date_created';

    protected string $username;
    protected bool $isActive;
    protected DateTime $dateCreated;
}