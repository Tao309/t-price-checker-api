<?php

namespace Core;

use Models\Entity;
use QueryPdo;
use Repository\Repository;

class EntityDataBuilder
{
    private const ALLOWABLE_PROPS_TYPES = [
        'bool',
        'int',
        'float',
        'string',
        'DateTime',
    ];

    private const NOT_UPDATABLE_PARAMS = [
        'date_updated',
        'date_created'
    ];

    private string $entityModel;
    private array $entityData;
    private array $preparedData;

    public function __construct(string $entityModel, array $entityData)
    {
        $this->entityModel = $entityModel;
        $this->entityData = $entityData;

        $this->processEntityData();
    }

    public function getQueryPreparedData(): array
    {
        return $this->preparedData;
    }

    /**
     * Получение значения из входящих данных по названию поля.
     *
     * @param string|array $param Входящие названия поля, полей.
     *
     * @return array|false|mixed Найденные значения.
     *
     * @throws \ReflectionException
     */
//    public function getEntityData(string|array $param): mixed
//    {
//        if (!isset($this->entityData[$param])) {
//            throw new \Exception('Property ' . $param . ' is not found in Entity Data');
//        }
//
//        $primaryClass = new \ReflectionClass('\\' . $this->entityModel);
//        $camelCaseParam = Entity::toCamelCase($param);
//        if (!$primaryClass->hasProperty($camelCaseParam)) {
//            throw new \Exception('Property ' . $param . ' is not exist in Entity');
//        }
//
//        $param = is_array($param) ? $param : [$param];
//
//        $values = [];
//        foreach ($param as $paramValue) {
//            $property = $primaryClass->getProperty($camelCaseParam);
//            $values[] = $this->prepareValue($property->getType()->getName(), $this->entityData[$paramValue]);
//        }
//
//        return count($values) > 1 ? $values: reset($values);
//    }

    private function processEntityData(): void
    {
        $preparedData = [];
        $primaryClass = new \ReflectionClass('\\' . $this->entityModel);
        $relToOne = $primaryClass->getConstant('RELATION_TO_ONE');
        $onlyReadProps = $primaryClass->getConstant('ONLY_READ_PROPERTIES');
        $primaryKeys = $primaryClass->getConstant('PRIMARY_KEY');
        $isNewModel = true;

        if ($primaryKeys === false) {
            throw new \Exception('PrimaryKey for entity '.$primaryClass->getName().' is required');
        }

        $primaryKeys = !is_array($primaryKeys) ? [$primaryKeys] : $primaryKeys;

        // Проверяем новая ли модель по входящим главным ключам модели.
        foreach ($primaryKeys as $primaryKey) {
            if (empty($this->entityData[$primaryKey])) {
                $isNewModel = false;
            }
        }

        foreach ($this->entityData as $param => $value) {
            $camelCaseParam = Entity::toCamelCase($param);

            if (!$primaryClass->hasProperty($camelCaseParam)) {
                continue;
            }

            $toData = false;
            $currentParam = $param;
            $property = $primaryClass->getProperty($camelCaseParam);
            $propertyType = $property->getType()->getName();

            // Для update не переписывать главные ключи.
            if (!$isNewModel && in_array($param, $primaryKeys)) {
                continue;
            }

            // Не пропускаем пустые значения главных ключей.
            if ($isNewModel && in_array($param, $primaryKeys) && empty($value)) {
                continue;
            }

            // Для существующей модели запрещаем обновление полей только для чтения
            if (!$isNewModel && is_array($onlyReadProps) && in_array($param, $onlyReadProps)) {
                continue;
            }

            if (in_array($param, self::NOT_UPDATABLE_PARAMS)) {
                continue;
            }

            if (is_array($relToOne)
                && isset($relToOne[$currentParam])
                && !isset($relToOne[$currentParam][Repository::PARAM_RELATION_USER_ID])
            ) {
                $toData = true;
                $param = $relToOne[$currentParam]['parent_id'];
                $value = $value[$relToOne[$currentParam]['relation_id']];
            } elseif (in_array($propertyType, self::ALLOWABLE_PROPS_TYPES)) {
                $toData = true;
                $value = $this->prepareValue($propertyType, $value);
            }

            if ($toData) {
                $preparedData[$param] = $value;
            }
        }

        $this->preparedData = $preparedData;
    }

    private function prepareValue(string $propertyType, $value): mixed
    {
        switch ($propertyType) {
            case 'bool':
                return (bool)$value;
            case 'int':
                return $value ? (int)$value : null;
            case 'float':
                return $value ? (float)$value : null;
            case 'string':
                return $value ? QueryPdo::escapeString($value) : null;
            case 'DateTime':
                if ($value === '1970-01-01T00:00:00.000Z') {
                    return null;
                }

                return $value;
            default:
                return null;
        }
    }

}