<?php

class QueryPdo
{
    static $connect;

    private $fields = [];
    private $fromTable = [];
    private $joins = [];
    private $where = [];
    private $group = [];
    private $limit = [];
    private $order = [];

    public function __construct()
    {

    }

    /**
     * @return QueryPdo
     */
    public static function initQueryPdo(): QueryPdo
    {
        return new QueryPdo();
    }

    /**
     * @return PDO
     */
    public static function getConnect(): PDO
    {
        if (!self::$connect) {
            $dsn = 'mysql:host=localhost;dbname=fr51790_tprice;charset=utf8';
            $user = 'fr51790_tprice';
            $password = 'Jg73fjkew3fgd';

            self::$connect = new \PDO($dsn, $user, $password, [PDO::ATTR_PERSISTENT => true]);
            self::$connect->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$connect;
    }

    public static function escapeString(string $value): string
    {
        return addslashes(stripslashes($value));
    }

    public function select($fields): self
    {
        $this->fields = is_array($fields) ? $fields : [$fields];

        return $this;
    }

    public function from($fromTable): self
    {
        $this->fromTable = is_array($fromTable) ? $fromTable : [$fromTable];

        return $this;
    }

    public function leftJoin($joinTable, $condition, $fields = null): self
    {
        $this->joins[] = [
            'type' => 'LEFT JOIN',
            'table' => is_array($joinTable) ? $joinTable : [$joinTable],
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
            'table' => is_array($joinTable) ? $joinTable : [$joinTable],
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

    public function where(string $condition): self
    {
        $this->where[] = ['AND', $condition];

        return $this;
    }

    public function orWhere(string $condition): self
    {
        $this->where[] = ['OR', $condition];

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
        $this->order[] = $column . ' ' . $dir;
        return $this;
    }

    public function assemble()
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

        foreach ($this->where as $index => $where) {
            if ($index === 0) {
                $query .= $br;
                $query .= 'WHERE ';
            }

            if ($index > 0) {
                $query .= $br;
                $query .= $where[0] . ' ';
            }

            $query .= $where[1];
        }

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

    public function update(string $table, array $values, string $condition): string
    {
        $query = 'UPDATE ' . $table . ' SET ';

        $queryValues = [];
        foreach ($values as $index => $value) {
            if (is_string($value)) {
                $value = '"' . $value . '"';
            } elseif ($value === null) {
                $value = 'NULL';
            } elseif ($value === false) {
                $value = 'FALSE';
            } elseif ($value === true) {
                $value = 'TRUE';
            }

            $queryValues[] = $index . ' = '.$value;
        }

        $query .= implode(', ', $queryValues);
        $query .= ' WHERE ' . $condition;

        return $query;
    }

    public function delete(string $table, array $conditions)
    {
        if (empty($conditions)) {
            throw new \Exception('Нет условий для удаления');
        }

        $query = 'DELETE FROM ' . $table;

        $where = [];
        foreach ($conditions as $index => $condition) {
            $where[] = $index . ' = ' . $condition;
        }

        $query .= ' WHERE ' . implode(' AND ', $where);

        return $query;
    }

    public function insert(string $tableName, array $arrayValues, string $onDuplicateKeyUpdate = null): string
    {
        $fields = [];
        $values = [];

        foreach ($arrayValues as $index => $arrayValue) {
            $fields[] = $index;

            if (is_string($arrayValue)) {
//                $arrayValue = '"' . $arrayValue . '"';
            }

            if ($arrayValue === null) {
                $arrayValue = 'null';
            }

            if ($arrayValue === false) {
                $arrayValue = 'false';
            }

            if ($arrayValue === true) {
                $arrayValue = 'true';
            }

            $values[] = $arrayValue;
        }

        $query = 'INSERT INTO ' . $tableName . '(' . implode(', ', $fields) . ')';
        $query .= ' VALUES(' . implode(', ', $values) . ')';

        if ($onDuplicateKeyUpdate) {
            $query .= ' ON DUPLICATE KEY UPDATE ' . $onDuplicateKeyUpdate;
        }

        return $query;
    }

    public function fetch(array $binds = [])
    {
        return $this->prepareFetch($binds)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array $binds
     * @return array|false
     */
    public function fetchAll(array $binds = []): array
    {
        $stmt = $this->prepareFetch($binds);
        try {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(\Throwable $e) {
            echo "\nfetchAll:\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }

    public function fetchColumn(array $binds = [])
    {
        $stmt = $this->prepareFetch($binds);
        try {
            return $stmt->fetchColumn();
        } catch(\Throwable $e) {
            echo "\nfetchColumn:\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }

    /**
     * @param array $binds
     * @return false|PDOStatement
     */
    private function prepareFetch(array $binds = []): PDOStatement
    {
        $dbh = QueryPdo::getConnect();

        $stmt = $dbh->prepare($this->assemble());

        foreach ($binds as $index => $value) {
            $stmt->bindValue(':' . $index, $value);
        }

        try {
            $stmt->execute();

            return $stmt;
        } catch(\Throwable $e) {
            echo "\prepareFetch:\n";
            echo $stmt->queryString . "\n";
            print_r($stmt->errorInfo());
            exit;
        }
    }
}