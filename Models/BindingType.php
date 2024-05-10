<?php

namespace Models;

/**
 * @method string getLabel()
 */
class BindingType extends Entity
{
    public const TABLE_PREFIX = 'bbt';
    public const TABLE_NAME = 'book_binding_type';

    protected const PROPERTIES = [
        self::PARAM_ID => 'ID',
        self::PARAM_LABEL => 'Тип переплёта',
    ];

    public const PARAM_LABEL = 'label';

    protected string $label;
}