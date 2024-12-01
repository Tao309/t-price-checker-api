<?php

namespace Core;

use Models\Entity;
use Models\User;
use QueryPdo;

class QueryBuilder
{
    private QueryPdo $queryPdo;
    private string $entityClassName;

    public function __construct(string $entityClassName)
    {
        $this->queryPdo = new queryPdo();
        $this->entityClassName = $entityClassName;

        $this->appendToQuery($this->entityClassName);
    }

    public function getQueryPdo(): QueryPdo
    {
        return $this->queryPdo;
    }

    /**
     * @param $entityClassName
     * @param string|null $namespace С точкой на конце.
     * @param array $relData Массив связи моделей, для дочерних.
     *
     * @throws \ReflectionException
     */
    private function appendToQuery(
        $entityClassName,
        string $namespace = null,
        string $parentTablePrefix = null,
        array $relData = []
    ): void
    {
        $currentNamespace = $namespace ? $namespace . '.' : '';

        $primaryClass = new \ReflectionClass('\\' . $entityClassName);
        $tableName = $primaryClass->getConstant('TABLE_NAME');
        $tablePrefix = $primaryClass->getConstant('TABLE_PREFIX') ?? $tableName;

        if ($tableName === User::TABLE_NAME && $parentTablePrefix) {
            $tablePrefix = $parentTablePrefix . '_' . $tablePrefix;
        }

        $selectValues = [];
        $selectValuesJoin = [];
        foreach ($primaryClass->getProperties() as $relProp) {
            $fieldName = Entity::toSnakeCase($relProp->getName());

            $properties = $primaryClass->getConstant('PROPERTIES');
            if (!is_array($properties) || !in_array($fieldName, array_keys($properties))) {
                continue;
            }

            $selectValues[] = $tablePrefix . '.' . $fieldName;
            $selectValuesJoin[] = $tablePrefix . '.' . $fieldName . ' AS \'' . $currentNamespace . $fieldName . '\'';
        }

        // Самая родительская модель.
        if (!$currentNamespace) {
            $this->getQueryPdo()
                ->select([join(', ', $selectValues)])
                ->from([$tablePrefix => $tableName]);
        } else {
            $joinType = isset($relData['foreign']) && $relData['foreign'] ? 'rightJoin' : 'leftJoin';

            $this->getQueryPdo()
                ->$joinType(
                    [$tablePrefix => $tableName],
                    sprintf(
                        '%s.%s = %s.%s',
                        $parentTablePrefix,
                        $relData['parent_id'],
                        $tablePrefix,
                        $relData['relation_id'] ?? Entity::PARAM_ID,
                    ),
                    $selectValuesJoin
                );
        }

        foreach ($primaryClass->getProperties() as $relProp) {
            $fieldName = Entity::toSnakeCase($relProp->getName());
            $className = $relProp->getType();
            $relNamespace = $namespace ? $namespace . '.' . $fieldName : $fieldName;

            if (!$className) {
                continue;
            }

            $relToOne = $primaryClass->getConstant('RELATION_TO_ONE');

            if (!is_array($relToOne) || !isset($relToOne[$fieldName])) {
                continue;
            }

            $className = str_replace(array('?', ' '), '', $className);

            $this->appendToQuery($className, $relNamespace, $tablePrefix, $relToOne[$fieldName]);
        }
    }
}