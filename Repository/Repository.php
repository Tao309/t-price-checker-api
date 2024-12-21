<?php

namespace Repository;

use Core\Config;
use Core\EntityDataBuilder;
use Core\QueryBuilder;
use Exception\CustomPdoException;
use Exception\ResponseException;
use Models\Entity;
use QueryPdo;
use ReflectionClass;

abstract class Repository
{
    public const PARAM_PARENT_ID = 'parent_id';
    public const PARAM_RELATION_ENTITY = 'relation_entity';
    public const PARAM_RELATION_ID = 'relation_id';
    public const PARAM_RELATION_USER_id = 'relation_user_id';

    protected string $entityModel = '';
    protected ?string $userDataRepositoryModel = null;

    private ReflectionClass $reflectionClass;

    public function __construct()
    {
        if (!$this->entityModel || !class_exists($this->entityModel)) {
            throw new \Exception('EntityModel for repository '.get_class($this).' is not found');
        }

        $this->reflectionClass = new ReflectionClass('\\' . $this->entityModel);
    }

    /**
     * Сохранение модели.
     *
     * @param array $data Входящие данные.
     *
     * @return array|int Primary ключ, ключи.
     *
     * @throws CustomPdoException
     * @throws ResponseException
     * @throws \ReflectionException
     */
    public function processSave(array $data): array|int
    {
        $primaryKeyNames = $this->getPrimaryKeyNames();
        $hasManyPrimaryKeys = count($primaryKeyNames) > 1;
        $primaryKeysValues = [];

        foreach ($primaryKeyNames as $primaryKeyName) {
            if (isset($data[$primaryKeyName]) && !empty($data[$primaryKeyName])) {
                $primaryKeysValues[] = $data[$primaryKeyName];
            }
        }

        if (empty($primaryKeysValues) && $hasManyPrimaryKeys) {
            throw new ResponseException('Primary keys not set for many primary keys');
        }

        $isNewModel = !$this->find($primaryKeysValues);

        $entityId = $isNewModel ? $this->processCreate($data) : $this->processUpdate($primaryKeysValues, $data);

        if ($hasManyPrimaryKeys) {
            return $entityId;
        }

        $entityId = reset($entityId);

        // Проверка наличия UserData в модели.
        $userDataClassName = $this->getUserDataClassName();
        if ($userDataClassName) {
            $userDataRepo = $this->getUserDataRepositoryInstance();

            $userDataFieldName = $this->getUserDataFieldParam();

            // Ранее в $data заходят другие данные.
            $data[$userDataFieldName][$this->getUserDataRelationIdParam()] = $entityId;
            $data[$userDataFieldName][$this->getUserDataRelationUserIdParam()] = Config::getCurrentUserid();

            $userDataRepo->processSave($data[$userDataFieldName]);
        }

        return $entityId;
    }

    /**
     * Обновление модели по входящим данным.
     *
     * @param array $data              Входящие данные.
     * @param array $primaryKeysValues Значения primary ключей модели.
     *
     * @return array|int Primary ключ, ключи.
     */
    public function processUpdate(array $primaryKeysValues, array $data): array|int
    {
        $className = $this->getReflectionCurrentModel();

//        if (!AccessRight::hasAccess(strtolower($className->getShortName()) . '.update')) {
//            throw new \RuntimeException('Update book is not granted');
//        }

        // Убираем из данных на обновления primary ключи.
        $primaryKeyNames = $this->getPrimaryKeyNames();
        foreach ($data as $index => $rowData) {
            if (in_array($index, $primaryKeyNames)) {
                unset($data[$index]);
            }
        }

        $entityDataBuilder = $this->getEntityDataBuilder($data);

        $query = (new QueryPdo())
            ->update(
                $this->getTableName(),
                $entityDataBuilder->getQueryPreparedData()
            );

        $this->appendWhereConditionByPrimaryKeys($primaryKeysValues, $query);

        try {
            $query->execute();

            return $primaryKeysValues;
        } catch(\PDOException $e) {
            throw new CustomPdoException($className->getShortName() . 'Repository.update', $query, $e);
        }

    }

    /**
     * Создание новой модели по входящим данным.
     *
     * @param array $data Входящие данные.
     *
     * @return array|int Primary ключ, ключи.
     */
    public function processCreate(array $data): array|int
    {
        $className = $this->getReflectionCurrentModel();

//        if (!AccessRight::hasAccess(strtolower($className->getShortName()) . '.create')) {
//            throw new \RuntimeException('Create book is not granted');
//        }

        $entityDataBuilder = $this->getEntityDataBuilder($data);

        $query = (new QueryPdo())
            ->insert($this->getTableName(), $entityDataBuilder->getQueryPreparedData());

        try {
            $query->execute();

            return $query->getLastInsertId();
        } catch(\PDOException $e) {
            throw new CustomPdoException($className->getShortName() . 'Repository.create', $query, $e);
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
        $query = $this->getListQueryNew();
        $this->appendWhereConditionByPrimaryKeys($primaryValueIds, $query, $this->getTablePrefix());

        $data = $query->fetch();

        $entityClassName = '\\' . $this->entityModel;
        $createMethod = 'create';
        $hasMethod = $this->getReflectionCurrentModel()->hasMethod($createMethod);

        if (!$hasMethod) {
            throw new ResponseException('Method "' . $createMethod . '" is not found in ' . $entityClassName);
        }

        $reflectionMethod = new \ReflectionMethod($entityClassName, $createMethod);

        if (!$reflectionMethod->isStatic()) {
            throw new ResponseException('Method "' . $createMethod . '" is not static in ' . $entityClassName);
        }

        return $data ? call_user_func([$entityClassName, 'create'], $data) : null;
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

    /**
     * Добавляем в запрос условия поиска по primary ключам.
     *
     * @param array $primaryValueIds Значения primary ключей, как они записаны в модели.
     * @param QueryPdo $query Запрос.
     * @param string $prefix Префкикс таблицы.
     *
     * @return void
     *
     * @throws ResponseException
     * @throws \ReflectionException
     */
    private function appendWhereConditionByPrimaryKeys(array $primaryValueIds, QueryPdo $query, $prefix = null): void
    {
        $primaryKeyNames = $this->getPrimaryKeyNames();
        $primaryClass = $this->getReflectionCurrentModel();

        if (count($primaryValueIds) != count($primaryKeyNames)) {
            throw new ResponseException('Не соответствие primary ключей модели ' . $primaryClass->getName());
        }

        $prefix = $prefix ? $prefix . '.' : '';

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

            $query->where($prefix . $primaryKeyName, ':' . $primaryKeyName);
            $query->bindParam($primaryKeyName, $primaryValueIds[$index]);
        }
    }

    /**
     * Получаем таблицы модели.
     *
     * @return string Название таблицы.
     *
     * @throws \ReflectionException
     */
    private function getTableName(): string
    {
        $primaryClass = $this->getReflectionCurrentModel();
        return $primaryClass->getConstant('TABLE_NAME');
    }

    /**
     * Получаем префикс таблицы модели.
     *
     * @return string Название префикса таблицы.
     *
     * @throws \ReflectionException
     */
    private function getTablePrefix(): string
    {
        $primaryClass = $this->getReflectionCurrentModel();
        return $primaryClass->getConstant('TABLE_PREFIX') ?? $primaryClass->getConstant('TABLE_NAME');
    }

    /**
     * Получаем рефексию текущей модели репозитория.
     *
     * @return \ReflectionClass
     *
     * @throws \ReflectionException
     */
    private function getReflectionCurrentModel(): \ReflectionClass
    {
        return $this->reflectionClass;
    }

    /**
     * Получаем primary ключи по модели.
     *
     * @return array Массив primary ключей.
     *
     * @throws \ReflectionException
     */
    private function getPrimaryKeyNames(): array
    {
        $primaryId = $this->getReflectionCurrentModel()->getConstant('PRIMARY_KEY');

        return is_array($primaryId) ? $primaryId : [$primaryId];
    }

    /**
     * Получаем название класса, который считается UserData к текущей модели.
     *
     * @return string|null Название класса модели UserDara.
     *
     * @throws \ReflectionException
     */
    private function getUserDataClassName(): string|null
    {
        $relationsToOne = $this->getReflectionCurrentModel()->getConstant('RELATION_TO_ONE');

        $userDataClassName = null;
        foreach ($relationsToOne as $relationToOne) {
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_id])) {
                $userDataClassName = $relationToOne[Repository::PARAM_RELATION_ENTITY];
                break;
            }
        }

        return $userDataClassName;
    }

    /**
     * Получаем названия поля UserdData в текущей модели.
     *
     * @return string|null Название поля UserData.
     *
     * @throws \ReflectionException
     */
    private function getUserDataFieldParam(): string|null
    {
        $relationsToOne = $this->getReflectionCurrentModel()->getConstant('RELATION_TO_ONE');

        $userDataFieldParam = null;
        foreach ($relationsToOne as $index => $relationToOne) {
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_id])) {
                $userDataFieldParam = $index;
                break;
            }
        }

        if (empty($userDataFieldParam)) {
            throw new ResponseException(
                'userDataFieldParam is empty for ' . $this->getReflectionCurrentModel()->getName()
            );
        }

        return $userDataFieldParam;
    }

    private function getUserDataRelationIdParam(): string|null
    {
        $relationsToOne = $this->getReflectionCurrentModel()->getConstant('RELATION_TO_ONE');

        $userDataRelationIdParam = null;
        foreach ($relationsToOne as $relationToOne) {
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_id])) {
                $userDataRelationIdParam = $relationToOne[Repository::PARAM_RELATION_ID];
                break;
            }
        }

        if (empty($userDataRelationIdParam)) {
            throw new ResponseException(
                'userDataRelationIdParam is empty for ' . $this->getReflectionCurrentModel()->getName()
            );
        }

        return $userDataRelationIdParam;
    }

    private function getUserDataRelationUserIdParam(): string|null
    {
        $relationsToOne = $this->getReflectionCurrentModel()->getConstant('RELATION_TO_ONE');

        $userDataRelationUserIdParam = null;
        foreach ($relationsToOne as $relationToOne) {
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_id])) {
                $userDataRelationUserIdParam = $relationToOne[Repository::PARAM_RELATION_USER_id];
                break;
            }
        }

        if (empty($userDataRelationUserIdParam)) {
            throw new ResponseException(
                'userDataRelationUserIdParam is empty for ' . $this->getReflectionCurrentModel()->getName()
            );
        }

        return $userDataRelationUserIdParam;
    }

    private function getUserDataRepositoryInstance(): Repository
    {
        if (empty($this->userDataRepositoryModel) || !class_exists('\\' . $this->userDataRepositoryModel)) {
            throw new ResponseException(
                sprintf(
                    'UserDataRepository for model %s is not found',
                    $this->getReflectionCurrentModel()->getName()
                )
            );
        }

        $userDataRepoClassName = '\\' . $this->userDataRepositoryModel;
        $userDataRepo = new $userDataRepoClassName();

        if (!method_exists($userDataRepo, 'find')) {
            throw new ResponseException('Method find does not exist in ' . $userDataRepoClassName);
        }

        if (!method_exists($userDataRepo, 'processSave')) {
            throw new ResponseException('Method processSave does not exist in ' . $userDataRepoClassName);
        }

        if (!method_exists($userDataRepo, 'processUpdate')) {
            throw new ResponseException('Method processUpdate does not exist in ' . $userDataRepoClassName);
        }

        if (!method_exists($userDataRepo, 'processCreate')) {
            throw new ResponseException('Method processCreate does not exist in ' . $userDataRepoClassName);
        }

        return $userDataRepo;
    }
}