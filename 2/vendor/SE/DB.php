<?php

namespace SE;

use \PDO as PDO;

class DB
{

    /* @var $lastQuery string */
    static public $lastQuery;
    /* @var $connect string */
    static public $connect;
    /* @var $dbSerial string */
    static public $dbSerial;
    /* @var $projectKey string */
    static public $projectKey;
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

    // сборка
    function __construct($tableName, $alias = null, $isCamelCaseMode = true)
    {
        $this->tableName = trim($tableName, "`");
        $this->aliasName = !empty($alias) ? $alias : $this->getAliasByTableName($tableName);
        $this->isCamelCaseMode = $isCamelCaseMode;
    }

    // задать
    function __set($name, $value)
    {
        if ($this->isCamelCaseMode)
            $name = $this->convertModelToField($name);
        $this->dataValues[$name] = $value;
    }

    // получить
    function __get($name)
    {
        if ($this->isCamelCaseMode)
            $name = $this->convertFieldToModel($name);
        if (isset($this->dataValues[$name]))
            return $this->dataValues[$name];
    }

    // в этом соединении
    public static function initConnection($connection)
    {
        try {
            self::$connect    = $connection;
            self::$dbSerial   = $connection['DBSerial'];
            self::$dbPassword = $connection['DBPassword'];
            self::$projectKey = $connection['ProjectKey'];
            self::$dbh = new PDO("mysql:host={$connection['HostName']};dbname={$connection['DBName']};charset=UTF8",
                $connection['DBUserName'], $connection['DBPassword'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            self::$dbh->exec('SET NAMES utf8');
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /*
     * Переподключение к БД
     * для экспорта/импорта больших баз
     */
    public function reConnection()
    {
        $connection = self::$connect;
        self::$dbh  = null;
        self::initConnection($connection);
    }

    /**
     * @param $errorMode int
     */
    // Установить режим ошибки
    public static function setErrorMode($errorMode)
    {
        if (self::$dbh)
            self::$dbh->setAttribute(PDO::ATTR_ERRMODE, $errorMode);
        else throw new Exception("The connection is not initialized!");
    }

    // получить таблицы
    public static function getTables()
    {
        if (self::$tables)
            return self::$tables;

        $stmt = self::$dbh->query('SHOW TABLES');
        $stmt->setFetchMode(PDO::FETCH_NUM);
        $items = $stmt->fetchAll();
        foreach ($items as $item)
            self::$tables[] = $item[0];
        return self::$tables;
    }

    // существующая таблица
    public static function existTable($tableName)
    {
        $tableName = trim($tableName, "`");
        return in_array($tableName, self::getTables());
    }

    // Начать транзакцию
    public static function beginTransaction()
    {
        if (self::$dbh)
            self::$dbh->beginTransaction();
        else throw new Exception("The connection is not initialized!");
    }

    // совершить
    public static function commit()
    {
        if (self::$dbh)
            self::$dbh->commit();
        else throw new Exception("The connection is not initialized!");
    }

    // откат
    public static function rollBack()
    {
        if (self::$dbh)
            self::$dbh->rollBack();
        else throw new Exception("The connection is not initialized!");
    }

    // запрос
    public static function query($statement)
    {
        if (self::$dbh) {
            self::$lastQuery = $statement;
            return self::$dbh->query($statement);
        } else throw new Exception("The connection is not initialized!");
    }

    // соединить
    public static function exec($statement)
    {
        if (self::$dbh) {
            self::$lastQuery = $statement;
            return self::$dbh->exec($statement);
        } else throw new Exception("The connection is not initialized!");
    }

    // котировка
    public static function quote($string)
    {
        if (self::$dbh) {
            return self::$dbh->quote($string);
        } else throw new Exception("The connection is not initialized!");
    }

    // подготовить
    public static function prepare($statement)
    {
        if (self::$dbh) {
            self::$lastQuery = $statement;
            return self::$dbh->prepare($statement);
        } else throw new Exception("The connection is not initialized!");
    }

    // добавить индекс
    public function add_index($field_name, $index = 1)
    {
        if (!DB::is_index($field_name)) {
            $index = ($index == 1) ? 'INDEX' : 'UNIQUE';
            DB::query("ALTER TABLE `{$this->tableName}` ADD {$index}(`{$field_name}`);");
        }
    }

    // поле
    public function is_field($field)
    {
        $sql = "SHOW COLUMNS FROM `{$this->tableName}` WHERE Field='$field'";
        $flist = DB::query($sql)->fetchAll();
        return (count($flist) > 0);
    }

    // индекс
    public function is_index($field_name, $name_index = '')
    {
        $key_index = ($name_index) ? " AND `Key_name`='{$name_index}'" : '';
        $sql = "SHOW INDEX FROM `{$this->tableName}` WHERE `Column_name` = '{$field_name}'" . $key_index;
        $flist = DB::query($sql)->fetchAll();
        return (count($flist) > 0);

    }

    // получить поле
    public function getField($field)
    {
        $sql = "SHOW COLUMNS FROM `{$this->tableName}` WHERE Field='{$field}'";
        try {
            $stmt = self::$dbh->query($sql);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            return $stmt->fetch();
        } catch (Exception $e) {
            throw new Exception("Отсутствует таблица {$this->tableName}");
        }
    }

    // добавить поле
    public function addField($field, $type = 'varchar(20)', $default = null, $index = 0)
    {
        $this->add_field($field, $type, $default, $index);
    }

    // добавить поле
    public function add_field($field, $type = 'varchar(20)', $default = null, $index = 0)
    {
        if (!$this->is_field($field)) {
            $type = str_replace(array('integer', 'string', 'integer(2)', 'integer(4)', 'bool', 'boolean'),
                array('int', 'varchar', 'int', 'bigint', 'tinyint(1)', 'tinyint(1)'), $type);
            if (preg_match("/float(\([\d\,]+\))?/u", $type, $m)) {
                $m[1] = preg_replace("/[\(\)]/", '', $m[1]);
                if (!empty($m[1])) {
                    list($dec,) = explode(',', $m[1]);
                    if (floatval($dec) < 8) $newType = 'float(' . $m[1] . ')';
                    else $newType = 'double(' . $m[1] . ')';
                } else $newType = 'double(10,2)';
                $type = str_replace($m[0], $newType, $type);
            }
            $after = '';
            $fields = $this->getFields();
            foreach ($fields as $fld) {
                $after = $fld['Field'];
            }
            if ($after) {
                $after = " AFTER `{$after}`";
            }
            if ($default !== null) {
                $default = ' default ' . $default . ' ';
            } else {
                $default = ' default NULL ';
            }
            //writeLog("ALTER TABLE `{$this->tableName}` ADD `{$field}` {$type}{$default}{$after};");
            DB::exec("ALTER TABLE `{$this->tableName}` ADD `{$field}` {$type}{$default}{$after}");
        }
        if ($index) {
            $this->add_index($field, $index);
        }
    }


    // строка к случаю верблюда?
    public static function strToCamelCase($str)
    {
        $separator = '_';
        $name = lcfirst(ucwords(str_replace($separator, ' ', $str)));
        return str_replace(" ", "", $name);
    }

    // подчеркнуть строку
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

    // вставить список
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

    // сохранить множество к множеству
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

    // получить поля
    public function getFields()
    {
        if ($this->fields)
            return $this->fields;

        try {
            $stmt = self::$dbh->query("SHOW COLUMNS FROM `{$this->tableName}`");
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            while ($row = $stmt->fetch())
                if (!in_array($row['Field'], array("updated_at", "created_at")))
                    $this->fields[$row['Field']] = $row;
        } catch (Exception $e) {
            throw new Exception("Отсутствует таблица {$this->tableName}");
        }

        return $this->fields;
    }

    // получить столбцы
    public function getColumns()
    {
        return array_keys($this->getFields());
    }

    // выбрать
    public function select($selectExpression)
    {
        $this->selectExpression = $selectExpression;
    }

    // внутреннее соединение
    public function innerJoin($tableName, $condition = null)
    {
        $this->join(0, $tableName, $condition);
    }

    // левое соединение
    public function leftJoin($tableName, $condition = null)
    {
        $this->join(1, $tableName, $condition);
    }

    // правое соединение
    public function rightJoin($tableName, $condition = null)
    {
        $this->join(2, $tableName, $condition);
    }

    // группа по...
    public function groupBy($field = null)
    {
        $this->groupBy = !empty($field) ? $field : $this->aliasName . ".id";
    }

    // сортировать по...
    public function orderBy($field = null, $desc = false)
    {
        $field = empty($field) ? $this->aliasName . ".id" : $field;
        $this->orderBy = array();
        $this->addOrderBy($field, $desc);
    }

    // добавить ордер от
    public function addOrderBy($field, $desc = false)
    {
        $this->orderBy[] = array("field" => $this->convertModelToField($field), "asc" => !$desc);
    }

    // получить список
    public function getListCount()
    {
        $result = $this->getListAggregation("COUNT(*) `count`");
        return (int)$result['count'];
    }

    // Получить агрегацию списков
    public function getListAggregation($statement)
    {
        $sql = "SELECT {$statement} FROM (" . $this->getSelectSql(true) . ") res_count";
        try {
            $stmt = self::$dbh->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $result = $stmt->fetch();
            return $result;
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    // Получить список
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
                    if ($this->isNumericField($key) && !is_null($value))
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

    // получить данные
    public function getInfo($id)
    {
        if (is_numeric($id))
            $this->where("{$this->aliasName}.id = ?", $id);
        $this->dataValues = $result = $this->getList(1);
        if (count($result))
            return $result[0];
        return null;
    }

    // найти
    public function find($id)
    {
        return $this->getInfo($id);
    }

    // задать предел
    public function setLimit($limit, $offset = null)
    {
        $this->offset = $offset;
        $this->limit = $limit;
    }

    // а также где
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

    // где
    public function where($where, $values = null)
    {
        $this->whereDefinitions = null;
        return $this->andWhere($where, $values);
    }

    // найти список
    public function findList($findText)
    {
        if (is_numeric($findText))
            $this->where("{$this->aliasName}.id = ?", $findText);
        else $this->where($findText);
        return $this;
    }

    // забрать один
    public function fetchOne()
    {
        $result = $this->getList(1);
        if (count($result))
            return $result[0];
        return null;
    }

    // удалить список
    public function deleteList()
    {
        try {
            self::$lastQuery = $this->rawQuery = $query = $this->getDeleteSql();
            return self::$dbh->exec($query);
        } catch (\PDOException $e) {
            throw new Exception($e->getMessage());
        }
    }

    // получить SQL
    public function getSql()
    {
        if (empty($this->rawQuery))
            return $this->getSelectSql();
        else return $this->rawQuery;
    }

    // выбрать полученную SQL
    function getSelectSql($countMode = false)
    {
        $result[] = "SELECT";
        $result[] = !empty($this->selectExpression) ? $this->selectExpression : "*";
        $result[] = "FROM";
        $result[] = "`{$this->tableName}`";
        $result[] = "`{$this->aliasName}`";
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

    // удалить полученную SQL
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

    // установить значение полей
    public function setValuesFields($values)
    {
        $this->inputData = $values;
        $fields = $this->getFields();
        $this->whereDefinitions = null;
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

    // сохранить
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
            if (!empty($this->dataValues["id"]))
                return $this->dataValues["id"];
        }

        $query[] = $isInsert ? "INSERT INTO" : "UPDATE";
        $query[] = $this->tableName;
        if (!empty($values)) {
            $query[] = "SET";
            $query[] = $values;
        } elseif ($isInsert)
            $query[] = "() VALUE ()";

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
            if (!empty($values))
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

    // является числовым полем
    private function isNumericField($name)
    {
        $fields = $this->getFields();
        foreach ($fields as $field) {
            if ($field["Field"] == $name)
                if (strpos($field["Type"], "int") !== false || strpos($field["Type"], "decimal") !== false ||
                    strpos($field["Type"], "float") !== false || strpos($field["Type"], "double") !== false
                )
                    return true;
        }
        return false;
    }

    // получить значение строки
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

    // установить значение поля
    private function setValueField($field, $value)
    {
        $type = $field["Type"];
        if ($type == "enum('Y','N')" && !is_string($value))
            $value = $value ? 'Y' : 'N';
        $this->dataValues[$field["Field"]] = $value;
    }

    /* @var $stmt \PDOStatement */
    // значения привязки
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

    // присоединить
    private function join($type, $tableName, $condition = null)
    {
        $this->joins[] = array("type" => $type, "name" => $tableName, "condition" => $condition);
    }

    // получить псевдоним по имени таблицы
    static public function getAliasByTableName($tableName)
    {
        $result = null;
        $tableName = trim($tableName, "`");
        $words = explode("_", $tableName);
        foreach ($words as $char)
            $result .= $char[0];
        return $result;
    }

    // преобразование поля в модель
    public function convertFieldToModel($name)
    {
        if (!$this->isCamelCaseMode)
            return $name;

        return self::strToCamelCase($name);
    }

    // конвертировать модель в поле
    public function convertModelToField($name)
    {
        if (!$this->isCamelCaseMode)
            return $name;

        return self::strToUnderscore($name);
    }
}