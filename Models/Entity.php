<?php

namespace Models;

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

    public function toArray(): array
    {
        $vars = get_object_vars($this);

        $m = [];
        foreach ($vars as $key => $value) {
            $m[$this->getSnakeCaseParam($key)] = $value;
        }

        unset($m['user_id'], $m['shop_id']);

        return $m;
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

    private function formatDateToZeroTimezone(string $dateString): string
    {
        $date = new \DateTime($dateString);
        $date->modify('+3 hours');

        return $date->format('Y-m-d H:i:s');
    }
}