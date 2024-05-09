<?php

namespace Models;

/**
 * @method string getLabel()
 */
class BindingType extends Entity
{
    public const PARAM_LABEL = 'label';

    public const RECORDABLE_PARAMS = [
        self::PARAM_LABEL,
    ];

    protected string $label;
}