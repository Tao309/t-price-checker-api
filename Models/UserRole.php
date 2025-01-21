<?php

namespace Models;

use DateTime;

/**
 * @method string getCode()
 * @method string getName()
 * @method DateTime getDateCreated()
 */
class UserRole extends Entity
{
    public const TABLE_PREFIX = 'ur';
    public const TABLE_NAME = 'user_roles';

    public const USER_ADMIN_ROLE = 'admin';
    public const USER_USER_ROLE = 'user';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_CODE => 'Код',
        self::PARAM_NAME => 'Название',
        self::PARAM_DATE_CREATED => 'Дата создания',
    ];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [

    ];

    public const PARAM_CODE = 'code';
    public const PARAM_NAME = 'Название';
    public const PARAM_DATE_CREATED = 'date_created';

    protected string $code;
    protected string $name;
    protected DateTime $dateCreated;
}