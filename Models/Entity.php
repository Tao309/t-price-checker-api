<?php

namespace Models;

use DateTime;

/**
 * @method int getId()
 */
abstract class Entity
{
    public const TABLE_PREFIX = null;
    public const TABLE_NAME = null;

    // Свойства модели с бд, их описание.
    protected const PROPERTIES = [];

    // Свойства, только для чтения, нельзя перезаписывать.
    protected const ONLY_READ_PROPERTIES = [];

    // Связь к одному.
    protected const RELATION_TO_ONE = [];

    // Связь ко многим.
    protected const RELATION_TO_MANY = [];

    public const PARAM_ID = 'id';
    public const PARAM_LABELS = 'labels';

    protected int $id;

    public function __construct(array $data)
    {
        $this->prepareDataToModel($data);

        $this->setDataToProperties($data);
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

    public static function toSnakeCase(string $param): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $param));
    }

    public function toArray(): array
    {
        $vars = get_object_vars($this);

        $m = [];
        foreach ($vars as $key => $varData) {
            if ($varData instanceof Entity) {
                $varData = $varData->toArray();
            } elseif ($varData instanceof DateTime) {
                $varData = $this->formatDateToZeroTimezone($varData);
            } elseif (is_array($varData)) {
                $varData = array_values(array_map(function ($oneVarData) {
                    if ($oneVarData instanceof Entity) {
                        return $oneVarData->toArray();
                    }

                    return $oneVarData;
                }, $varData));
            }

            $m[self::toSnakeCase($key)] = $varData;
        }

        unset(
            $m['relation_to_one'],
            $m['relation_to_many']
        );

        $m[self::PARAM_LABELS] = static::PROPERTIES;

        return $m;
    }

    public function getLabelName(string $prop)
    {
        return static::PROPERTIES[$prop] ?? null;
    }

    protected function formatDateToZeroTimezone(DateTime $date): string
    {
//        $date = new DateTime($dateString);
        $date->modify('+3 hours');

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Обходим по массиву данных и обрабатываем индексы с точками.
     *
     * @param array $data Входные данные.
     *
     * @return void
     */
    protected function prepareDataToModel(array &$data): void
    {
        foreach ($data as $index => $value) {
            $path = explode('.', $index);
            if (count($path) < 2) {
                continue;
            }

            unset($data[$index]);

            if (is_null($value)) {
                continue;
            }

            $firstPath = $path[0];
            $secondPath = $path[1];

            if (!isset($data[$firstPath])) {
                $data[$firstPath] = [];
            }

            if (count($path) < 3) {
                $data[$firstPath][$secondPath] = $value;
                continue;
            }

            array_shift($path);

            $key = implode('.', $path);
            $data[$firstPath][$key] = $value;

            $this->prepareDataToModel($data[$firstPath]);
        }
    }

    /**
     * Установка значений в свойства модели.
     *
     * @param array $data Входящие данные.
     *
     * @return void
     *
     * @throws \Exception
     */
    private function setDataToProperties(array $data): void
    {
        $class = new \ReflectionClass($this);

        foreach ($data as $param => $value) {
            $camelCaseParam = $this->getCamelCaseParam($param);

            if ($class->hasProperty($camelCaseParam)) {
                $property = $class->getProperty($camelCaseParam);
                $propertyType = $property->getType()->getName();
                //echo $camelCaseParam . ': '. $propertyType . ' | ';

                switch ($propertyType) {
                    case 'bool':
                    case 'string':
                    case 'int':
                        $this->$camelCaseParam = $value;
                        break;
                    case 'DateTime':
                        if (!is_null($value)) {
                            $this->$camelCaseParam = new DateTime($value);
                        }
                        break;
                    case 'array':
                        if (isset(static::RELATION_TO_MANY[$param])) {
                            $relationClassName = static::RELATION_TO_MANY[$param]['relation_entity'];

                            $this->$camelCaseParam = array_map(function ($relationData) use ($relationClassName) {
                                return new $relationClassName($relationData);
                            }, $value);
                        } else {
                            $this->$camelCaseParam = $value;
                        }
                        break;
                    default:
                        if (isset(static::RELATION_TO_ONE[$param])) {
                            $relationClassName = static::RELATION_TO_ONE[$param]['relation_entity'];
                            $this->$camelCaseParam = new $relationClassName($value);
                        }
                }
            }
        }
    }

    private function getCamelCaseParam(string $snakeCaseParam): string
    {
        $snakeCaseParam = mb_convert_case($snakeCaseParam, MB_CASE_TITLE, "UTF-8");

        return lcfirst(str_replace('_', '', $snakeCaseParam));
    }

}