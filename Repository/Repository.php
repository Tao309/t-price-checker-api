<?php

namespace Repository;

use Core\EntityDataBuilder;
use Core\QueryBuilder;
use QueryPdo;

abstract class Repository
{
    protected string $entityModel = '';

    public function __construct()
    {
        if (!$this->entityModel || !class_exists($this->entityModel)) {
            throw new \Exception('EntityModel for repository '.get_class($this).' is not found');
        }
    }

    protected function getEntityDataBuilder(array $data): EntityDataBuilder
    {
        return new EntityDataBuilder($this->entityModel, $data);
    }

    protected function getListQueryNew(): QueryPdo
    {
        $qb = new QueryBuilder($this->entityModel);

        return $qb->getQueryPdo();
    }

    /**
     * Преобразование массива полей в тип 'field_name' => ':field_name'
     * @param array $values
     * @return void
     */
    protected function assembleInsertValues(array $values): array
    {
        $arrayValues = [];
        foreach($values as $param) {
            $arrayValues[$param] = ':' . $param;
        }

        return $arrayValues;
    }
}