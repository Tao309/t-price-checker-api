<?php

class QueryPdo
{
    public const EXPR_IS_NULL = 'is_null';
    public const EXPR_IS_NOT_NULL = 'is_not_null';

    private const QUERY_TYPE_SELECT = 'select_type';
    private const QUERY_TYPE_UPDATE = 'update_type';
    private const QUERY_TYPE_INSERT = 'insert_type';
    private const QUERY_TYPE_DELETE = 'delete_type';

    static $connect;

    private ?string $tableName = null;
    private ?string $queryType = null;
    private ?string $onDuplicateKeyUpdate = null;
    private array $preparedData;
    private array $bindParams = [];
    private array $tablePrefixes = [];
    private ?PDOStatement $stmt;

    private $fields = [];
    private $fromTable = [];
    private $joins = [];
    private $where = [];
    private $group = [];
    private $limit = [];
    private $order = [];

    public function __construct()
    {
        $this->preparedData = [];
        $this->bindParams = [];
    }

    /**
     * @return PDO
     */
    public static function getConnect(): PDO
    {
        if (!self::$connect) {
            $dsn = sprintf(
                'mysql:host=localhost;dbname=%s;charset=utf8',
                getenv('DB_TABLE')
            );
            $user = getenv('DB_USER');
            $password = getenv('DB_PASSWORD');

            self::$connect = new \PDO($dsn, $user, $password, [PDO::ATTR_PERSISTENT => true]);
            self::$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$connect;
    }

    public static function beginTransaction(): void
    {
        $dbh = self::getConnect();
        $dbh->beginTransaction();
    }

    public static function commit(): void
    {
        $dbh = self::getConnect();
        $dbh->commit();
    }

    public static function rollBack(): void
    {
        $dbh = self::getConnect();
        $dbh->rollBack();
    }

    /**
     * Добавляем в запрос выборка по параметрам.
     *
     * @param array       $params Входящие параметры: название поля => значение.
     * @param string|null $prefix Префикс для таблицы.
     *
     * @return self
     *
     * @throws Exception
     */
    public function appendWhereConditionByParams(array $params = [], string $prefix = null): self
    {
        $prefix = $prefix ? $prefix . '.' : '';

        foreach ($params as $param => $value) {
            $this->where($prefix . $param, ':' . $param);
            $this->bindParam($param, is_array($value) ? implode(',',$value) : $value);
        }

        return $this;
    }

    public function execute(): PDOStatement
    {
        $dbh = self::getConnect();
        $this->stmt = $dbh->prepare($this->assemble());
        $this->stmt->execute($this->getBindParams() ?? null);

        return $this->stmt;
    }

    public function getRowCount(): int
    {
        return $this->stmt->rowCount();
    }

    public function getLastInsertId(): int
    {
        return self::getConnect()->lastInsertId();
    }

    public function getStmt(): PDOStatement
    {
        return $this->stmt;
    }

    public function getBindParams(): array
    {
        return $this->bindParams;
    }

    public function bindParams(array $bindParams): self
    {
        $this->bindParams = array_merge($this->bindParams, $bindParams);

        return $this;
    }

    public function bindParam(string $param, $value): self
    {
        $this->bindParams[$param] = $value;

        return $this;
    }

    public static function escapeString(string $value): string
    {
        return addslashes(stripslashes($value));
    }

    public function select($fields = null): self
    {
        $this->queryType = self::QUERY_TYPE_SELECT;

        if (!empty($fields)) {
            $this->fields = is_array($fields) ? $fields : [$fields];
        }

        return $this;
    }

    public function from($fromTable): self
    {
        $this->setTable($fromTable);

        return $this;
    }

    private function setTable($fromTable): void
    {
        $this->fromTable = $this->getJoinTable($fromTable);
        $this->tableName = $this->fromTable[array_key_first($this->fromTable)];
    }

    private function getJoinTable($joinTable): array
    {
        if (is_array($joinTable)) {
            $prefix = array_key_first($joinTable);
            if (is_int($prefix)) {
                throw new \Exception('QueryPdo: fromTable prefix can not be a integer.');
            }

            $this->addTablePrefix($prefix, $joinTable[$prefix]);

            return $joinTable;
        }

        if (!is_string($joinTable)) {
            throw new \Exception('QueryPdo: fromTable prefix auto can not be a integer.');
        }

        $this->addTablePrefix($joinTable, $joinTable);

        return [$joinTable => $joinTable];
    }

    protected function getFromTablePrefix(): string
    {
        if (empty($this->fromTable)) {
            throw new \Exception('QueryPdo: fromTable is empty');
        }

        return array_key_first($this->fromTable);
    }

    public function leftJoin($joinTable, $condition, $fields = null): self
    {
        $this->joins[] = [
            'type' => 'LEFT JOIN',
            'table' => $this->getJoinTable($joinTable),
            'condition' => $condition,
        ];

        if (!empty($fields)) {
            if (is_array($fields)) {
                $this->fields = array_merge($this->fields, $fields);
            } else {
                array_push($this->fields, $fields);
            }
        }

        return $this;
    }

    public function rightJoin($joinTable, $condition, $fields = null): self
    {
        $this->joins[] = [
            'type' => 'RIGHT JOIN',
            'table' => $this->getJoinTable($joinTable),
            'condition' => $condition,
        ];

        if (!empty($fields)) {
            if (is_array($fields)) {
                $this->fields = array_merge($this->fields, $fields);
            } else {
                array_push($this->fields, $fields);
            }
        }

        return $this;
    }

    public function where(string $name, $value = ''): self
    {
        if (!empty($value) || is_bool($value) || is_int($value) || ($value instanceof QueryPdo)) {
            $this->where[] = ['AND', $this->processWhereCondition($name, $value)];
        } else {
            $this->where[] = ['AND', $name];
        }

        return $this;
    }

    public function orWhere(string $name, $value = ''): self
    {
        if (!empty($value)) {
            $this->where[] = ['OR', $this->processWhereCondition($name, $value)];
        } else {
            $this->where[] = ['OR', $name];
        }

        return $this;
    }

    public function group(string $groupValue): self
    {
        $this->group[] = $groupValue;

        return $this;
    }

    public function limit(int $offset, int $limit = null): self
    {
        $limitArray = [$offset];
        if ($limit) {
            $limitArray[] = $limit;
        }

        $this->limit = $limitArray;

        return $this;
    }

    public function order($column, $dir = 'ASC'): self
    {
        $columnSplit = explode('.', $column);
        if (count($columnSplit) < 2) {
            $column = $this->getFromTablePrefix() . '.' . $column;
        }

        $this->order[] = $column . ' ' . $dir;
        return $this;
    }

    public function assemble(): string
    {
        if (empty($this->queryType)) {
            throw new \Exception('Query Type is empty');
        }

        if (empty($this->tableName)) {
            throw new \Exception('TableName is empty');
        }

        switch ($this->queryType) {
            case self::QUERY_TYPE_SELECT:
                return $this->assembleSelectQuery();
            case self::QUERY_TYPE_INSERT:
                return $this->assembleInsertQuery();
            case self::QUERY_TYPE_UPDATE:
                return $this->assembleUpdateQuery();
            case self::QUERY_TYPE_DELETE:
                return $this->assembleDeleteQuery();
        }

        throw new \Exception('Assemble is not support for query type ' . $this->queryType);
    }

    public function update(string $tableName, array $values): self
    {
        $this->queryType = self::QUERY_TYPE_UPDATE;
        $this->setTable($tableName);

        foreach ($values as $index => $value) {
            if (is_null($value)) {
                $value = 'NULL';
            } elseif ($value === false) {
                $value = 'FALSE';
            } elseif ($value === true) {
                $value = 'TRUE';
            } elseif(is_string($value)) {
                $value = '"' . $value . '"';
            }

            $this->preparedData[$index] = $value;
        }

        return $this;
    }

    public function delete(string $tableName): self
    {
        $this->queryType = self::QUERY_TYPE_DELETE;
        $this->setTable($tableName);

        return $this;
    }

    public function insert(string $tableName, array $preparedData, string $onDuplicateKeyUpdate = null): self
    {
        $this->queryType = self::QUERY_TYPE_INSERT;
        $this->setTable($tableName);
        $this->onDuplicateKeyUpdate = $onDuplicateKeyUpdate;
        $this->preparedData = $preparedData;

        foreach ($preparedData as $index => $preparedValue) {
            if (is_string($preparedValue)) {
//                $preparedValue = '"' . $preparedValue . '"';
            }

            if ($preparedValue === null) {
                $preparedValue = 'null';
            }

            if ($preparedValue === false) {
                $preparedValue = 'false';
            }

            if ($preparedValue === true) {
                $preparedValue = 'true';
            }

//            $this->preparedData[$index] = $preparedValue;
        }

        $this->bindParams($this->preparedData);

        return $this;
    }

    public function fetch()
    {
        return $this->prepareFetch()->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchAll(): array
    {
        $stmt = $this->prepareFetch();
        try {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(\Throwable $e) {
            echo "\nfetchAll:\n";
            echo $e->getMessage() . "\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }

    public function fetchColumn()
    {
        $stmt = $this->prepareFetch();
        try {
            return $stmt->fetchColumn();
        } catch(\Throwable $e) {
            echo "\nfetchColumn:\n";
            echo $e->getMessage() . "\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }

    public function getPreparedData(): array
    {
        if (empty($this->preparedData)) {
            throw new \Exception('queryPreparedData is empty');
        }

        return $this->preparedData;
    }

    public function getTablePrefix(string $tableName): string|null
    {
        if (!isset($this->tablePrefixes[$tableName])) {
            throw new \Exception('TablePrefix for "' . $tableName . '" is empty');
        }

        return $this->tablePrefixes[$tableName];
    }

    private function addTablePrefix(string $prefixName, string $tableName): void
    {
        $this->tablePrefixes[$tableName] = $prefixName;
    }

    private function processWhereCondition($name, $value = ''): string
    {
        $nameSplit = explode('.', $name);
        if (count($nameSplit) < 2) {
            $name = $this->getFromTablePrefix() . '.' . $name;
        }

        if ($value instanceof QueryPdo) {
            return $name . ' IN (' . $value->assemble() . ')';
        }

        if (is_array($value)) {
            return $name . ' IN (' . implode(",", array_values($value)) . ')';
        }

        if (is_bool($value)) {
            return $name . ' = ' . (int)$value;
        }

        if (strpos($value, ':') === 0) {
            return $name . ' = ' . trim($value);
        }

        return match ($value) {
            self::EXPR_IS_NULL => $name . ' IS NULL',
            self::EXPR_IS_NOT_NULL => $name . ' IS NOT NULL',
            default => $name . ' = "' . trim($value) . '"',
        };
    }

    private function prepareFetch(): PDOStatement
    {
        $dbh = QueryPdo::getConnect();

        $stmt = $dbh->prepare($this->assemble());

        foreach ($this->getBindParams() as $index => $value) {
            $stmt->bindValue(':' . $index, $value);
        }

        try {
            $stmt->execute();

            return $stmt;
        } catch(\Throwable $e) {
            echo "\prepareFetch:\n";
            echo $e->getMessage() . "\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }

    private function assembleSelectQuery(): string
    {
        $br = ' ';

        $query = 'SELECT ' . implode(', ', $this->fields);

        $query .= $br;
        $firstKey = array_key_first($this->fromTable);
        $query .= 'FROM ' . $this->fromTable[$firstKey];
        if (is_string($firstKey)) {
            $query .= ' AS ' . $firstKey;
        }

        foreach ($this->joins AS $join) {
            $query .= $br;
            $firstKey = array_key_first($join['table']);
            $query .= $join['type'] . ' ' . $join['table'][$firstKey];

            if (is_string($firstKey)) {
                $query .= ' AS ' . $firstKey;
            }

            $query .= ' ON (' . $join['condition'] . ')';
        }

        $query .= $this->getWhereCondition(false);

        if (!empty($this->group)) {
            $query .= $br;
            $query .= 'GROUP BY ' . implode(',', $this->group);
        }

        if (!empty($this->order)) {
            $query .= $br;
            $query .= 'ORDER BY ' . implode(',', $this->order);
        }

        if (!empty($this->limit)) {
            $query .= $br;
            $query .= 'LIMIT ' . implode(',', $this->limit);
        }

        return $query;
    }

    private function assembleInsertQuery(): string
    {
        $query = 'INSERT INTO ' . $this->tableName . '(' . implode(', ', array_keys($this->getPreparedData())) . ')';
        $query .= ' VALUES(' . implode(', ', $this->getQueryKeysVariables()) . ')';

        if ($this->onDuplicateKeyUpdate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $this->onDuplicateKeyUpdate;
        }

        return $query;
    }

    private function assembleUpdateQuery(): string
    {
        $query = 'UPDATE ' . $this->tableName . ' SET ';

        $values = [];
        foreach ($this->getPreparedData() as $index => $value) {
            $values[] = $index . ' = '. $value;
        }

        $query .= implode(', ', $values);

        $query .= $this->getWhereCondition();

        return $query;
    }

    private function assembleDeleteQuery(): string
    {
        $query = 'DELETE FROM ' . $this->tableName;
        $query .= $this->getWhereCondition();

        return $query;
    }

    private function getWhereCondition($checkExists = true): string
    {
        if ($checkExists && empty($this->where)) {
            throw new \Exception('Empty where condition for update query');
        }

        $br = ' ';
        $query = '';

        foreach ($this->where as $index => $where) {
            if ($index === 0) {
                $query .= $br;
                $query .= ' WHERE ';
            }

            if ($index > 0) {
                $query .= $br;
                $query .= $where[0] . ' ';
            }

            $query .= $where[1];
        }

        return $query;
    }

    private function getQueryKeysVariables(): array
    {
        $vars = [];

        foreach ($this->getPreparedData() as $key => $value) {
            $vars[$key] = ':' . $key;
        }

        return $vars;
    }
}