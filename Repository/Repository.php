<?php

namespace Repository;

use Core\AccessRight\AccessRight;
use Core\ArrayHandler;
use Core\Config;
use Core\EntityDataBuilder;
use Core\QueryBuilder;
use Exception\CustomPdoException;
use Exception\NoRightsException;
use Exception\ResponseException;
use Models\Book;
use Models\Entity;
use Models\Product;
use Models\SourceProduct;
use QueryPdo;
use ReflectionClass;

abstract class Repository
{
    public const PARAM_PARENT_ID = 'parent_id';
    public const PARAM_RELATION_ENTITY = 'relation_entity';
    public const PARAM_RELATION_ID = 'relation_id';
    public const PARAM_RELATION_USER_ID = 'relation_user_id';
    public const PARAM_ORDER = 'order';
    public const PARAM_ORDER_DIRECTION = 'order_direction';
    public const PARAM_LIMIT = 'limit';

    private const PARAM_SORT_DIRECTION_ASC = 'ASC';
    private const PARAM_SORT_DIRECTION_DESC = 'DESC';

    private const PARAM_METHOD_CREATE = 'create';

    protected string $entityModel = '';
    protected ?string $userDataRepositoryModel = null;

    private ReflectionClass $reflectionClass;
    private $toSaveUserData = true;

    // Выводим в дебаг показа собранного запроса.
    private $debugQuery = false;

    // Кэшированные модели при запросах.
    private static array $cacheModels = [];

    public function __construct()
    {
        if (!$this->entityModel || !class_exists($this->entityModel)) {
            throw new \Exception('EntityModel for repository '.get_class($this).' is not found');
        }

        $this->reflectionClass = new ReflectionClass('\\' . $this->entityModel);
    }

    public function enableDebugQuery(): void
    {
        $this->debugQuery = true;
    }

    public function disabledDebugQuery(): void
    {
        $this->debugQuery = false;
    }

    public function isDebugQueryEnabled(): bool
    {
        return $this->debugQuery;
    }

    public function setToSaveUserData(bool $toSaveUserData): void
    {
        $this->toSaveUserData = $toSaveUserData;
    }

    public function getToSaveUserData(): bool
    {
        return $this->toSaveUserData;
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
     * @throws NoRightsException
     */
    public function save(array $data): array|int
    {
        $primaryKeyNames = $this->getPrimaryKeyNames();
        $hasManyPrimaryKeys = count($primaryKeyNames) > 1;
        $primaryKeysValues = [];

        // Проставляю ключи из входящих данных в массив primary ключей по индексу.
        foreach ($primaryKeyNames as $index => $primaryKeyName) {
            if (isset($data[$primaryKeyName]) && !empty($data[$primaryKeyName])) {
                $primaryKeysValues[$index] = $data[$primaryKeyName];
            }
        }

        $isNewModel = true;

        if (count($primaryKeysValues)) {
            $isNewModel = !$this->find($primaryKeysValues);
        }

        try {
            if ($isNewModel) {
                $entityId = $this->create($data);
            } else {
                $entityId = $this->update($primaryKeysValues, $data);
            }
        } catch (NoRightsException $e) {
            // Для новой модели, должны быть права на create, иначе не к чему привязывать userData потом.
            if ($isNewModel) {
                throw $e;
            }

            // При update возвращается массив.
            $entityId = $primaryKeysValues;
        }

        if ($hasManyPrimaryKeys) {
            return $entityId;
        }

        $entityId = $isNewModel ? $entityId : reset($entityId);

        // Проверка наличия UserData в модели.
        $userDataClassName = $this->getUserDataClassName();
        if ($userDataClassName && $this->getToSaveUserData()) {
            $userDataRepo = $this->getUserDataRepositoryInstance();

            $userDataFieldName = $this->getUserDataFieldParam();
            $data[$userDataFieldName][$this->getUserDataRelationIdParam()] = $entityId;
            $data[$userDataFieldName][$this->getUserDataRelationUserIdParam()] = Config::getCurrentUserid();

            $userDataRepo->save($data[$userDataFieldName]);
        }

        return $entityId;
    }

    /**
     * Преобразует входящие ключи в массив: название поля => значение.
     *
     * @param array|int $primaryIds
     *
     * @return array<string, int>
     *
     * @throws ResponseException
     * @throws \ReflectionException
     */
    private function preparePrimaryKeys(array|int $primaryIds): array
    {
        $primaryIds = is_array($primaryIds) ? $primaryIds : [$primaryIds];

        $primaryKeyNames = $this->getPrimaryKeyNames();
        $hasManyPrimaryKeys = count($primaryKeyNames) > 1;
        $primaryKeysValues = [];
        $countPrimaryKeysValues = count($primaryKeysValues);

        foreach ($primaryKeyNames as $index => $primaryKeyName) {
            if (isset($primaryIds[$index]) && !empty($primaryIds[$index])) {
                $primaryKeysValues[$primaryKeyName] = (int)$primaryIds[$index];
            }
        }

        if (empty($primaryKeysValues) && $hasManyPrimaryKeys) {
            throw new ResponseException('Primary keys not set for many primary keys');
        }

        if ($countPrimaryKeysValues > 0 && $countPrimaryKeysValues !== count($primaryKeysValues)) {
            throw new ResponseException('Не соответствие primary ключей модели с их значениями');
        }

        return $primaryKeysValues;
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
//        if (!is_array($primaryId)) {
//            $cacheModel = $this->getCacheModel($primaryId);
//
//            if ($cacheModel) {
//                return $cacheModel;
//            }
//        }
        $primaryId = $this->preparePrimaryKeys($primaryId);

        $rows = $this->findByParams(
            $this->getWhereConditionsByPrimaryKeys($primaryId),
            [
                self::PARAM_LIMIT => 1
            ]
        );

        if (!$rows) {
            return null;
        }

        return reset($rows);
    }

    /**
     * Получить модели по входящих параметрам.
     *
     * @param array $params Параметры для where: тип => значение.
     * @param array $filters Фильтры (order, order_direction, limit).
     *
     * @return Entity[] Массив моделей.
     *
     * @throws ResponseException
     * @throws \ReflectionException
     */
    public function findByParams(array $params, array $filters = []): array
    {
        $query = $this->getQuery();
        $query->appendWhereConditionByParams($params, $this->getTablePrefix());

        if (ArrayHandler::hasParam(self::PARAM_ORDER, $filters)) {
            $orderDirection = ArrayHandler::hasParam(self::PARAM_ORDER_DIRECTION, $filters)
                ? ArrayHandler::getValueAsString(self::PARAM_ORDER_DIRECTION, $filters)
                : self::PARAM_SORT_DIRECTION_ASC;

            $query->order(ArrayHandler::getValueAsString(self::PARAM_ORDER, $filters), $orderDirection);
        }

        if (ArrayHandler::hasParam(self::PARAM_LIMIT, $filters)) {
            $query->limit(ArrayHandler::getValueAsInt(self::PARAM_LIMIT, $filters));
        }

        if ($this->isDebugQueryEnabled()) {
            $this->showDebugQuery($query);
        }

        $rows = $query->fetchAll();

        $models = [];
        foreach ($rows as $row) {
            $model = $this->transformRowDataToModel($row);
//            $this->addToCache($model);
            $models[] = $model;
        }

        return $models;
    }

    protected function getEntityDataBuilder(array $data): EntityDataBuilder
    {
        return new EntityDataBuilder($this->entityModel, $data);
    }

    /**
     * Обновление модели по входящим данным.
     *
     * @param array|int $primaryId         Значения primary ключа (ключей).
     * @param array     $primaryKeysValues Значения primary ключей модели.
     *
     * @return array|int Primary ключ, ключи.
     *
     * @throws CustomPdoException
     * @throws NoRightsException
     * @throws ResponseException
     * @throws \ReflectionException
     */
    public function update(array|int $primaryId, array $data): array|int
    {
        if (in_array($this->getReflectionCurrentModel()->getName(), [
            Book::class,
            Product::class,
            SourceProduct::class,
        ])) {
            AccessRight::checkAccess(Entity::toSnakeCase($this->getReflectionCurrentModel()->getShortName()) . '.update');
        }

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


        $primaryId = $this->preparePrimaryKeys($primaryId);

        $query->appendWhereConditionByParams(
            $this->getWhereConditionsByPrimaryKeys($primaryId)
        );

        try {
            $query->execute();

            return $primaryId;
        } catch(\PDOException $e) {
            throw new CustomPdoException(
                $this->getReflectionCurrentModel()->getShortName() . 'Repository.update',
                $query,
                $e
            );
        }

    }

    /**
     * Создание новой модели по входящим данным.
     *
     * @param array $data Входящие данные.
     *
     * @return array|int Primary ключ, ключи.
     *
     * @throws CustomPdoException
     * @throws NoRightsException
     * @throws ResponseException
     * @throws \ReflectionException
     */
    public function create(array $data): array|int
    {
        if (in_array($this->getReflectionCurrentModel()->getName(), [
            Book::class,
            Product::class,
            SourceProduct::class,
        ])) {
            AccessRight::checkAccess(Entity::toSnakeCase($this->getReflectionCurrentModel()->getShortName()) . '.create');
        }

        $entityDataBuilder = $this->getEntityDataBuilder($data);
        $preparedData = $entityDataBuilder->getQueryPreparedData();

        $requiredFields = $this->getReflectionCurrentModel()->getConstant('WHEN_CREATE_REQUIRED_PROPERTIES');
        if (is_array($requiredFields)) {
            foreach ($requiredFields as $requiredField) {
                if (empty($preparedData[$requiredField])) {
                    throw new ResponseException(sprintf(
                        'Property %s is required for %s',
                        $requiredField,
                        $this->getReflectionCurrentModel()->getShortName()
                    ));
                }
            }
        }

        $query = (new QueryPdo())
            ->insert($this->getTableName(), $preparedData);

        try {
            $query->execute();

            return $query->getLastInsertId();
        } catch(\PDOException $e) {
            throw new CustomPdoException(
                $this->getReflectionCurrentModel()->getShortName() . 'Repository.create',
                $query,
                $e
            );
        }
    }

    protected function getQuery(): QueryPdo
    {
        return (new QueryBuilder($this->entityModel))->getQueryPdo();
    }

    /**
     * Преобразование массива полей в тип 'field_name' => ':field_name'
     *
     * @param array $values
     *
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
     * Преборазование массива данных в модель.
     *
     * @param array $rowData Массив данных для будущем модели.
     *
     * @return Entity Модель.
     *
     * @throws ResponseException
     * @throws \ReflectionException
     */
    private function transformRowDataToModel(array $rowData): Entity
    {
        $entityClassName = '\\' . $this->entityModel;
        $hasMethod = $this->getReflectionCurrentModel()->hasMethod(self::PARAM_METHOD_CREATE);

        if (!$hasMethod) {
            throw new ResponseException('Method "' . self::PARAM_METHOD_CREATE . '" is not found in ' . $entityClassName);
        }

        $reflectionMethod = new \ReflectionMethod($entityClassName, self::PARAM_METHOD_CREATE);

        if (!$reflectionMethod->isStatic()) {
            throw new ResponseException('Method "' . self::PARAM_METHOD_CREATE . '" is not static in ' . $entityClassName);
        }

        return call_user_func([$entityClassName, self::PARAM_METHOD_CREATE], $rowData);
    }

    /**
     * Получение $params для подстановки в запрос.
     *
     * @param array|int $primaryValueIds Массив primary ключей: поле (Entity:PARAM_Id) => значение (5).
     *
     * @return array
     *
     * @throws ResponseException
     * @throws \ReflectionException
     */
    private function getWhereConditionsByPrimaryKeys(array|int $primaryValueIds): array
    {
        if (!is_array($primaryValueIds)) {
            // Добавить получение этого поля primary ключа?
            return [
                Entity::PARAM_ID => $primaryValueIds
            ];
        }

        $primaryKeyNames = $this->getPrimaryKeyNames();
        $primaryClass = $this->getReflectionCurrentModel();

        if (count($primaryValueIds) != count($primaryKeyNames)) {
            throw new ResponseException('Не соответствие primary ключей модели ' . $primaryClass->getName());
        }

        $params = [];
        foreach ($primaryKeyNames as $index => $primaryKeyName) {
            if (!isset($primaryValueIds[$primaryKeyName])) {
                throw new ResponseException(
                    sprintf(
                        'Не найден в модели %s primary ключ для поля %s (index: %s) (multiple)',
                        $primaryClass->getName(),
                        $primaryKeyName,
                        $index
                    )
                );
            }

            $params[$primaryKeyName] = $primaryValueIds[$primaryKeyName];
        }

        return $params;
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
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_ID])) {
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
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_ID])) {
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
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_ID])) {
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
            if (isset($relationToOne[Repository::PARAM_RELATION_USER_ID])) {
                $userDataRelationUserIdParam = $relationToOne[Repository::PARAM_RELATION_USER_ID];
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

        if (!method_exists($userDataRepo, 'save')) {
            throw new ResponseException('Method save does not exist in ' . $userDataRepoClassName);
        }

        if (!method_exists($userDataRepo, 'update')) {
            throw new ResponseException('Method update does not exist in ' . $userDataRepoClassName);
        }

        if (!method_exists($userDataRepo, 'create')) {
            throw new ResponseException('Method create does not exist in ' . $userDataRepoClassName);
        }

        return $userDataRepo;
    }

    private function showDebugQuery(QueryPdo $query): void
    {
        $preparedData = [];

        try {
            $preparedData = $query->getPreparedData();
        } catch(\Throwable) {}

        logMe([
            'query' => $query->assemble(),
            'bind_params' => $query->getBindParams(),
            'prepared_data' => $preparedData
        ]);exit;
    }

    private function addToCache(Entity $model): void
    {
        $key = $this->entityModel . '-' . $model->getId();
        self::$cacheModels[$key] = $model;
    }

    private function getCacheModel(int $id)
    {
        $key = $this->entityModel . '-' . $id;
        return self::$cacheModels[$key] ?? null;
    }
}