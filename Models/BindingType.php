<?php

namespace Models;

/**
 * @method string getLabel()
 */
class BindingType extends Entity
{
    public const PARAM_LABEL = 'label';

    protected string $label;
}