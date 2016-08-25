<?php

namespace SE;

use \PDO as PDO;

class DB
{

    /* @var $lastQuery string */
    static public $lastQuery;
    /* @var $dbSerial string */
    static public $dbSerial;
    /* @var $dbPassword string */
    static public $dbPassword;
    /* @var $dbh PDO */
    static protected $dbh = null;
    static private $tables = array();

    private $isCamelCaseMode = true;
    /* @var $lastQuery string */
    private $rawQuery;

    /* @var $tableName string */
    protected $tableName;
    /* @var $aliasName string */
    protected $aliasName;
    /* @var $selectExpression string */
    protected $selectExpression;
    /* @var $groupBy string */
    protected $groupBy;
    protected $orderBy = array();
    protected $joins = array();
    /* @var $limit integer */
    protected $limit;
    /* @var $offset integer */
    protected $offset;
    /* @var $whereDefinitions string */
    protected $whereDefinitions;
    protected $whereValues = array();
    protected $dataValues = array();
    protected $inputData = array();

    private $fields = array();

    function __construct($tableName, $alias = null, $isCamelCaseMode = true)
    {
        $this->tableName = $tableName;
        $this->aliasName = !empty($alias) ? $alias : $this->getAliasByTableName($tableName);
        $this->isCamelCaseMode = $isCamelCaseMode;
    }

    function __set($name, $value)
    {
        if ($this->isCamelCaseMode)
            $name = $this->convertModelToField($name);
        $this->dataValues[$name] = $value;
    }

    function __get($name)
    {
        if ($this->isCamelCaseMode)
            $name = $this->convertFieldToModel($name);
        if (isset($this->dataValues[$name]))
            return $this->dataValues[$name];
    }

    public static function initConnection($connection)
    {
        try {
            self::$dbSerial = $connection['DBSerial'];
            self::$dbPassword = $connection['DBPassword'];
            self::$dbh = new PDO("mysql:host={$connection['HostName']};dbname={$connection['DBName']}",
                $connection['DBUserName'], $connection['DBPassword'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param $errorMode int
     */
    public static function setErrorMode($errorMode)
    {
        if (self::$dbh)
            self::$dbh->setAttribute(PDO::ATTR_ERRMODE, $errorMode);
        else throw new Exception("The connection is not initialized!");
    }

    public static function getTables()
    {
        if (self::$tables)
            return self::$tables;

        $stmt = self::$dbh->query('SHOW TABLES');
        $stmt->setFetchMode(PDO::FETCH_NUM);
        self::$tables[] = $stmt->fetchAll();
        return self::$tables;
    }

    public static function beginTransaction()
    {
        if (self::$dbh)
            self::$dbh->beginTransaction();
        else throw new Exception("The connection is not initialized!");
    }

    public static function commit()
    {
        if (self::$dbh)
            self::$dbh->commit();
        else throw new Exception("The connection is not initialized!");
    }

    public static function rollBack()
    {
        if (self::$dbh)
            self::$dbh->rollBack();
        else throw new Exception("The connection is not initialized!");
    }

    public static function query($statement)
    {
        if (self::$dbh) {
            self::$lastQuery = $statement;
            return self::$dbh->query($statement);
        } else throw new Exception("The connection is not initialized!");
    }

    public static function exec($statement)
    {
        if (self::$dbh) {
            self::$lastQuery = $statement;
            return self::$dbh->exec($statement);
        } else throw new Exception("The connection is not initialized!");
    }

    public static function prepare($statement)
    {
        if (self::$dbh) {
            self::$lastQuery = $statement;
            return self::$dbh->prepare($statement);
        } else throw new Exception("The connection is not initialized!");
    }

    public static function strToCamelCase($str)
    {
        $separator = '_';
        $name = lcfirst(ucwords(str_replace($separator, ' ', $str)));
        return str_replace(" ", "", $name);
    }

    public static function strToUnderscore($str)
    {
        $separator = '_';
        $result = null;
        for ($i = 0; $i < strlen($str); $i++) {
            if (ctype_upper($str[$i]))
                $result .= $separator;
            $result .= $str[$i];
        }
        return strtolower($result);
    }

    public static function insertList($tableName, $data, $isIgnoreMode = false)
    {
        if (empty($data) || !is_array($data))
            return false;

        try {
            reset($data);
            $query[] = $isIgnoreMode ? 'INSERT IGNORE INTO' : 'INSERT INTO';
            $query[] = $tableName;
            $query[] = "SET";
            $fields = array();
            while (list($columns,) = each($data[0])) {
                $columns = str_replace('`', '', $columns);
                $fields[] = '`' . str_replace('`', '', $columns) . '` = :' . $columns;
            }
            $fields = implode(", ", $fields);
            $query[] = $fields;
            $sql = implode(" ", $query);
            self::$lastQuery = $sql;
            $stmt = self::$dbh->prepare($sql);
            foreach ($data as $values) {
                foreach ($values as $key => $value) {
                    $type = PDO::PARAM_STR;
                    if (is_numeric($value))
                        $type = PDO::PARAM_INT;
                    if (is_bool($value))
                        $type = PDO::PARAM_BOOL;
                    $stmt->bindValue(":{$key}", $value, $type);
                }
                $stmt->execute();
            }
            return true;
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function saveManyToMany($idKey, $links = array(), $setting = array())
    {
        try {
            $existIds = array();
            $sql = "SELECT {$setting['link']} FROM {$setting["table"]} WHERE {$setting['table']}.{$setting['key']} = {$idKey}";
            $items = DB::query($sql)->fetchAll();
            foreach ($items as $item)
                if (!empty($item[$setting["link"]]))
                    $existIds[] = $item[$setting["link"]];

            $deleteIds = array();
            foreach ($existIds as $id) {
                $isFind = false;
                foreach ($links as $link) {
                    $isFind = $link["id"] == $id;
                    if ($isFind)
                        break;
                }
                if (!$isFind)
                    $deleteIds[] = $id;
            }
            if ($deleteIds) {
                $ids = implode(",", $deleteIds);
                DB::exec("DELETE FROM {$setting['table']} WHERE 
                                  {$setting['table']}.{$setting['key']} = {$idKey} AND {$setting['link']} IN ({$ids})");
            }

            $newLinks = array();
            $updateLinks = array();
            foreach ($links as $link) {
                $item = empty($setting["isSort"]) ? array("id" => $link["id"]) :
                    array("id" => $link["id"], "sort" => $link["sort"]);
                if (!in_array($link["id"], $existIds))
                    $newLinks[] = $item;
                else $updateLinks[] = $item;
            }
            if ($newLinks) {
                $sql = array();
                foreach ($newLinks as $link)
                    $sql[] = empty($setting["isSort"]) ? "({$link["id"]}, {$idKey})" :
                        "({$link["id"]}, {$idKey}, {$link["sort"]})";
                $sql = (empty($setting["isSort"]) ?
                        "INSERT INTO {$setting['table']} ({$setting['link']}, {$setting['key']}) VALUES " :
                        "INSERT INTO {$setting['table']} ({$setting['link']}, {$setting['key']}, sort) VALUES ") . implode(",", $sql);
                DB::exec($sql);
            }
            if ($updateLinks && !empty($setting["isSort"])) {
                $sql = array();
                foreach ($updateLinks as $link)
                    $sql[] = "UPDATE {$setting['table']} SET sort = {$link["sort"]} 
                              WHERE ({$setting['link']} = {$link["id"]} AND {$setting['key']} = {$idKey})";
                $sql = implode(";\n", $sql);
                DB::exec($sql);
            }

        } catch (\PDOException $e) {
            throw new Exception("Query: " . self::$lastQuery . "\nError: " . $e->getMessage());
        }
    }

    public static function quote($str)
    {
        return self::$dbh->quote($str);
    }

    public function getFields()
    {
        if ($this->fields)
            return $this->fields;

        $stmt = self::$dbh->query("SHOW COLUMNS FROM `{$this->tableName}`");
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $stmt->fetch())
            if (!in_array($row['Field'], array("updated_at", "created_at")))
                $this->fields[$row['Field']] = $row;
        return $this->fields;
    }

    public function getColumns()
    {
        return array_keys($this->getFields());
    }

    public function select($selectExpression)
    {
        $this->selectExpression = $selectExpression;
    }

    public function innerJoin($tableName, $condition = null)
    {
        $this->join(0, $tableName, $condition);
    }

    public function leftJoin($tableName, $condition = null)
    {
        $this->join(1, $tableName, $condition);
    }

    public function rightJoin($tableName, $condition = null)
    {
        $this->join(2, $tableName, $condition);
    }

    public function groupBy($field = null)
    {
        $this->groupBy = !empty($field) ? $field : $this->aliasName . ".id";
    }

    public function orderBy($field = null, $desc = false)
    {
        $field = empty($field) ? $this->aliasName . ".id" : $field;
        $this->orderBy = array();
        $this->addOrderBy($field, $desc);
    }

    public function addOrderBy($field, $desc = false)
    {
        $this->orderBy[] = array("field" => $this->convertModelToField($field), "asc" => !$desc);
    }

    public function getListCount()
    {
        return $this->getListAggregation("COUNT(*)");
    }

    public function getListAggregation($statement)
    {
        $sql = "SELECT {$statement} FROM (" . $this->getSelectSql(true) . ") res_count";
        try {
            $stmt = self::$dbh->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_NUM);
            $result = $stmt->fetch();
            return $result[0];
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getList($limit = null, $offset = null)
    {
        if ($limit)
            $this->setLimit($limit, $offset);
        $sql = $this->getSelectSql();
        try {
            self::$lastQuery = $this->rawQuery = $sql;
            $stmt = self::$dbh->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $items = array();
            while ($row = $stmt->fetch()) {
                $item = array();
                foreach ($row as $key => $value) {
                    if (is_numeric($value) && strpos($value, "0") !== 0)
                        $value += 0;
                    $item[$this->convertFieldToModel($key)] = $value;
                }
                $items[] = $item;
            }
            return $items;
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getInfo($id)
    {
        if (is_numeric($id))
            $this->where("{$this->aliasName}.id = ?", $id);
        $this->dataValues = $result = $this->getList(1);
        if (count($result))
            return $result[0];
        return null;
    }

    public function find($id)
    {
        return $this->getInfo($id);
    }

    public function setLimit($limit, $offset = null)
    {
        $this->offset = $offset;
        $this->limit = $limit;
    }

    public function andWhere($where, $values = null)
    {
        if (empty($where))
            return $this;

        if (!is_array($values)) {
            $value = $values;
            $where = str_replace("?", $value, $where);
        }
        if (!empty($this->whereDefinitions))
            $this->whereDefinitions = "{$this->whereDefinitions} AND ({$where})";
        else $this->whereDefinitions = $where;
        if (is_array($values))
            $this->whereValues = $values;
        return $this;
    }

    public function where($where, $values = null)
    {
        $this->whereDefinitions = null;
        return $this->andWhere($where, $values);
    }

    public function findList($findText)
    {
        if (is_numeric($findText))
            $this->where("{$this->aliasName}.id = ?", $findText);
        else $this->where($findText);
        return $this;
    }

    public function fetchOne()
    {
        $result = $this->getList(1);
        if (count($result))
            return $result[0];
        return null;
    }

    public function deleteList()
    {
        try {
            self::$lastQuery = $this->rawQuery = $query = $this->getDeleteSql();
            return self::$dbh->exec($query);
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getSql()
    {
        if (empty($this->rawQuery))
            return $this->getSelectSql();
        else return $this->rawQuery;
    }

    function getSelectSql($countMode = false)
    {
        $result[] = "SELECT";
        $result[] = !empty($this->selectExpression) ? $this->selectExpression : "*";
        $result[] = "FROM";
        $result[] = $this->tableName;
        $result[] = $this->aliasName;
        if ($this->joins) {
            foreach ($this->joins as $join) {
                switch ($join["type"]) {
                    case 0:
                        $result[] = "INNER";
                        break;
                    case 1:
                        $result[] = "LEFT";
                        break;
                    case 2:
                        $result[] = "RIGHT";
                        break;
                }
                $result[] = "JOIN";
                $result[] = $join["name"];
                $result[] = "ON";
                $result[] = $join["condition"];
            }
        }
        if (!empty($this->whereDefinitions)) {
            $result[] = "WHERE";
            $result[] = $this->whereDefinitions;
        }
        if (!empty($this->groupBy)) {
            $result[] = "GROUP BY";
            if (strpos($this->groupBy, ".") === false)
                $this->groupBy = "{$this->aliasName}.$this->groupBy";
            $result[] = $this->groupBy;
        }
        if (!$countMode) {
            if (!empty($this->orderBy)) {
                $result[] = "ORDER BY";
                $orders = array();
                foreach ($this->orderBy as $orderBy) {
                    $field = $orderBy["field"];
                    if (!$orderBy["asc"])
                        $field .= " DESC";
                    $orders[] = $field;
                }
                $result[] = implode(",", $orders);
            }
            if ($this->limit) {
                $result[] = "LIMIT";
                if ($this->offset)
                    $result[] = "{$this->offset},";
                $result[] = $this->limit;
            }
        }
        return implode(" ", $result);
    }

    function getDeleteSql()
    {
        if (empty($this->whereDefinitions))
            return null;

        $result[] = "DELETE";
        $result[] = "FROM";
        $result[] = $this->tableName;
        $result[] = "WHERE";
        $result[] = $this->whereDefinitions;

        return implode(" ", $result);
    }

    public function setValuesFields($values)
    {
        $this->inputData = $values;
        $fields = $this->getFields();
        foreach ($values as $key => $value) {
            if (($key == "id" && empty($value)) || (is_array($value) && $key != "ids") || is_object($value))
                continue;

            if (is_array($value) && $key == "ids") {
                $ids = implode(",", $value);
                $this->whereDefinitions = "id IN ($ids)";
            }
            $keyName = $key;
            if (key_exists($keyName, $fields)) {
                $this->setValueField($fields[$keyName], $value);
                continue;
            }
            $keyName = $this->convertModelToField($keyName);
            if (key_exists($keyName, $fields))
                $this->setValueField($fields[$keyName], $value);
        }
    }

    public function save($isInsertId = false)
    {
        if (empty($this->tableName))
            return null;

        $isInsert = !key_exists("id", $this->dataValues) && empty($this->whereDefinitions);
        $isInsert = $isInsert || $isInsertId && key_exists("id", $this->dataValues);
        if ($isInsert && key_exists("id", $this->dataValues) && !empty($this->dataValues["id"])) {
            $values = $this->dataValues;
            $object = $this->getInfo($this->dataValues["id"]);
            $this->whereDefinitions = null;
            $isInsert = is_null($object);
            $this->dataValues = $values;
        }
        $values = $this->getValuesString($isInsert, $isInsertId);
        if (empty($values)) {
            if (!empty($this->inputData["ids"]))
                $this->dataValues["id"] = $this->inputData["ids"][0];
            return $this->dataValues["id"];
        }

        $query[] = $isInsert ? "INSERT INTO" : "UPDATE";
        $query[] = $this->tableName;
        $query[] = "SET";
        $query[] = $values;
        if (!$isInsert) {
            $query[] = "WHERE";
            if (empty($this->whereDefinitions))
                $this->where("id = :id", array("id" => $this->dataValues["id"]));
            $query[] = $this->whereDefinitions;
        }

        try {
            $sql = implode($query, " ");
            self::$lastQuery = $this->rawQuery = $sql;
            $stmt = self::$dbh->prepare($sql);
            $this->bindValues($stmt);
            if ($stmt->execute()) {
                if ($isInsert && !$isInsertId)
                    return self::$dbh->lastInsertId();
                else {
                    if (!empty($this->inputData["ids"]))
                        $this->dataValues["id"] = $this->inputData["ids"][0];
                    return $this->dataValues["id"];
                }
            } else return null;
        } catch (\PDOException $e) {
            throw new Exception("Query: " . self::$lastQuery . "\nError: " . $e->getMessage());
        }
    }

    private function getValuesString($isInsert, $isInsertId = false)
    {
        $result = array();
        foreach ($this->dataValues as $field => $value) {
            if ($isInsert && !$isInsertId && in_array($field, array("id", "ids")))
                continue;
            if (!$isInsert && in_array($field, array("id", "ids")) && empty($this->whereDefinitions))
                continue;
            $result[] = "`{$field}` = :{$field}";
        }
        return implode(", ", $result);
    }

    private function setValueField($field, $value)
    {
        $type = $field["Type"];
        if ($type == "enum('Y','N')" && !is_string($value))
            $value = $value ? 'Y' : 'N';
        $this->dataValues[$field["Field"]] = $value;
    }

    /* @var $stmt \PDOStatement */
    private function bindValues($stmt)
    {
        $values = array_merge($this->dataValues, $this->whereValues);
        foreach ($values as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_numeric($value))
                $type = PDO::PARAM_INT;
            if (is_bool($value))
                $type = PDO::PARAM_BOOL;
            $stmt->bindValue(":{$key}", $value, $type);
        }
    }

    private function join($type, $tableName, $condition = null)
    {
        $this->joins[] = array("type" => $type, "name" => $tableName, "condition" => $condition);
    }

    private function getAliasByTableName($tableName)
    {
        $result = null;
        $words = explode("_", $tableName);
        foreach ($words as $char)
            $result .= $char[0];
        return $result;
    }

    public function convertFieldToModel($name)
    {
        if (!$this->isCamelCaseMode)
            return $name;

        return self::strToCamelCase($name);
    }

    public function convertModelToField($name)
    {
        if (!$this->isCamelCaseMode)
            return $name;

        return self::strToUnderscore($name);
    }
}