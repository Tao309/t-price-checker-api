<?php

namespace Models;

use DateTime;

/**
 * @method string getUsername()
 * @method bool getIsActive()
 * @method UserRole getUserRole()
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

    protected const RELATION_TO_ONE = [
        self::PARAM_USER_ROLE => [
            'parent_id' => self::PARAM_USER_ROLE_ID,
            'relation_entity' => UserRole::class,
            'relation_id' => Entity::PARAM_ID,
        ],
    ];

    public const PARAM_USERNAME = 'username';
    public const PARAM_IS_ACTIVE = 'is_active';
    public const PARAM_USER_ROLE_ID = 'user_role_id';
    public const PARAM_DATE_CREATED = 'date_created';

    // От зависимых моделей.
    public const PARAM_USER_ROLE = 'user_role';

    protected string $username;
    protected bool $isActive;
    protected UserRole $userRole;
    protected DateTime $dateCreated;
}