<?php

namespace Repository;

use Core\EntityDataBuilder;
use Core\QueryBuilder;
use Exception\ResponseException;
use Models\Book;
use Models\Entity;
use Models\Product;
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

    /**
     * Получение модели по primary ключу/ключам.
     *
     * @param array|int $primaryId Значения primary ключа (ключей).
     *
     * @return Entity|null Модель.
     *
     * @throws ResponseException
     * @throws \ReflectionException
     */
    public function find(array|int $primaryId): Entity|null
    {
        $primaryValueIds = !is_array($primaryId) ? [$primaryId] : array_values($primaryId);
        $primaryKeyNames = $this->getPrimaryKeyNames();
        $primaryClass = new \ReflectionClass('\\' . $this->entityModel);

        if (count($primaryValueIds) != count($primaryKeyNames)) {
            throw new ResponseException('Не соответствие primary ключей модели ' . $primaryClass->getName());
        }

        $primaryClass = new \ReflectionClass('\\' . $this->entityModel);
        $tablePrefix = $primaryClass->getConstant('TABLE_PREFIX') ?? $primaryClass->getConstant('TABLE_NAME');

        $query = $this->getListQueryNew();
        foreach ($primaryKeyNames as $index => $primaryKeyName) {
            if (!isset($primaryValueIds[$index])) {
                throw new ResponseException(
                    sprintf(
                        'Не найден в модели %s primary ключ для поля %s',
                        $primaryClass->getName(),
                        $primaryKeyName
                    )
                );
            }

            $query->where($tablePrefix . '.' . $primaryKeyName, ':' . $primaryKeyName);
            $query->bindParam($primaryKeyName, $primaryValueIds[$index]);
        }

        $data = $query->fetch();

        // @todo метод create проверять, что есть?
        return $data ? call_user_func(['\\' . $this->entityModel, 'create'], $data) : null;
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

    private function getPrimaryKeyNames(): array
    {
        $primaryClass = new \ReflectionClass('\\' . $this->entityModel);

        $primaryId = $primaryClass->getConstant('PRIMARY_KEY');

        return is_array($primaryId) ? $primaryId : [$primaryId];
    }
}