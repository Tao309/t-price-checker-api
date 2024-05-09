<?php

namespace Models;

/**
 * @method int getId()
 */
abstract class Entity
{
    public const PARAM_ID = 'id';

    public const RECORDABLE_PARAMS = [];
    public const RECORDABLE_BOOLEAN_PARAMS = [];
    public const RECORDABLE_DATETIME_PARAMS = [];

    protected int $id;

    public function __construct(array $data)
    {
        foreach ($data as $param => $value) {
            $camelCaseParam = $this->getCamelCaseParam($param);

            if ($param === 'id') {
                $this->id = $value;
                continue;
            }

            if (in_array($param, static::RECORDABLE_PARAMS)) {
                $this->$camelCaseParam = $value;
                continue;
            }

            if (in_array($param, static::RECORDABLE_BOOLEAN_PARAMS)) {
                $this->$camelCaseParam = (bool)$value;
                continue;
            }

            if (!empty($value) && in_array($param, static::RECORDABLE_DATETIME_PARAMS)) {
                $this->$camelCaseParam = $this->formatDateToZeroTimezone($value);
                continue;
            }
        }
    }

    public function __call($methodName, $arguments)
    {
        $methodPrefix = substr($methodName, 0, 3);
        $prop = self::toCamelCase(substr($methodName, 3));

        if (!property_exists($this, $prop)) {
            throw new \Exception('Property ' . $prop . ' is not exists.');
        }

        if ($methodPrefix === 'set' && count($arguments) == 1) {
            $value = $arguments[0];
            $this->{$prop} = $value;

            return $this;
        }

        if ($methodPrefix === 'get') {
            return $this->{$prop};
        }

        throw new \Exception('Method ' . $methodPrefix . ' is not defined.');
    }

    public static function toCamelCase($str, array $noStrip = array()): string
    {
        $str = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $str);
        $str = trim($str);
        $str = ucwords($str);
        $str = str_replace(" ", "", $str);
        $str = lcfirst($str);

        return $str;
    }

    public function toArray(): array
    {
        $vars = get_object_vars($this);

        $m = [];
        foreach ($vars as $key => $varData) {
            if ($varData instanceof Entity) {
                $varData = $varData->toArray();
            } elseif (is_array($varData)) {
                $varData = array_values(array_map(function ($oneVarData) {
                    if ($oneVarData instanceof Entity) {
                        return $oneVarData->toArray();
                    }

                    return $oneVarData;
                }, $varData));
            }

            $m[$this->getSnakeCaseParam($key)] = $varData;
        }

        return $m;
    }

    protected function formatDateToZeroTimezone(string $dateString): string
    {
        $date = new \DateTime($dateString);
        $date->modify('+3 hours');

        return $date->format('Y-m-d H:i:s');
    }

    private function getCamelCaseParam(string $snakeCaseParam): string
    {
        $snakeCaseParam = mb_convert_case($snakeCaseParam, MB_CASE_TITLE, "UTF-8");

        return lcfirst(str_replace('_', '', $snakeCaseParam));
    }

    private function getSnakeCaseParam(string $param): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $param));
    }
}