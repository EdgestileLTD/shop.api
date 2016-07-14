<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Base
{
    protected $result;
    protected $input;
    protected $error;
    protected $statusAnswer = 200;
    protected $isTableMode = true;
    protected $limit = 100;
    protected $offset = 0;
    protected $sortBy = "id";
    protected $sortOrder = "desc";
    protected $availableFields;
    protected $filterFields;
    protected $protocol = 'http';
    protected $search;
    protected $filters = array();
    protected $hostname;
    protected $urlImages;
    protected $dirImages;
    protected $tableName;
    protected $tableAlias;
    protected $imageSize = 256;
    protected $imagePreviewSize = 64;
    protected $availableSigns = array("=", "<=", "<", ">", ">=", "IN");

    private $patterns = array();

    function __construct($input = null)
    {
        $input = $this->input = empty($input) || is_array($input) ? $input : json_decode($input, true);
        $this->hostname = HOSTNAME;
        $this->limit = $input["limit"] && $this->limit ? (int) $input["limit"] : $this->limit;
        $this->offset = $input["offset"] ? (int)$input["offset"] : $this->offset;
        $this->sortOrder = $input["sortOrder"] ? $input["sortOrder"] : $this->sortOrder;
        $this->sortBy = $input["sortBy"] ? $input["sortBy"] : $this->sortBy;
        $this->search = $input["searchText"] ? $input["searchText"] : null;
        $this->filters = empty($this->input["filters"]) || !is_array($this->input["filters"]) ?
            array() : $this->input["filters"];
        $this->input["ids"] = empty($this->input["ids"]) ?
            (empty($this->input["id"]) ? null : array($this->input["id"])) : $this->input["ids"];
        if (empty($this->tableAlias) && !empty($this->tableName)) {
            $worlds = explode("_", $this->tableName);
            foreach ($worlds as $world)
                $this->tableAlias .= $world[0];
        }
    }

    function __set($name, $value)
    {
        if (is_array($this->input))
            $this->input[$name] = $value;
    }

    function __get($name)
    {
        if (is_array($this->input) && isset($this->input[$name]))
            return $this->input[$name];
    }

    public function initConnection($connection)
    {
        try {
            DB::initConnection($connection);
            return true;
        } catch (Exception $e) {
            $this->error = 'Не удаётся подключиться к базе данных!';
            return false;
        }
    }

    public function output()
    {
        if (!empty($this->error) && $this->statusAnswer == 200)
            $this->statusAnswer = 500;
        switch ($this->statusAnswer) {
            case 200: {
                echo json_encode($this->result);
                exit;
            }
            case 404: {
                header("HTTP/1.1 404 Not found");
                echo $this->error;
                exit;
            }
            case 500: {
                header("HTTP/1.1 500 Internal Server Error");
                echo $this->error;
                exit;
            }
        }
    }

    public function setFilters($filters)
    {
        $this->filters = empty($filters) || !is_array($filters) ? array() : $filters;
    }

    private function createTableForInfo($settings)
    {
        $u = new DB($this->tableName, $this->tableAlias);
        $u->select($settings["select"]);

        if (!empty($settings["joins"])) {
            if (!empty($settings["joins"]["type"]))
                $settings["joins"] = array($settings["joins"]);
            foreach ($settings["joins"] as $join) {
                $join["type"] = strtolower(trim($join["type"]));
                if ($join["type"] == "inner")
                    $u->innerJoin($join["table"], $join["condition"]);
                if ($join["type"] == "left")
                    $u->leftJoin($join["table"], $join["condition"]);
                if ($join["type"] == "right")
                    $u->rightJoin($join["table"], $join["condition"]);
            }
        }
        return $u;
    }

    public function fetch()
    {
        $settingsFetch = $this->getSettingsFetch();

        $settingsFetch["select"] = $settingsFetch["select"] ? $settingsFetch["select"] : "*";
        $this->patterns = $this->getPattensBySelect($settingsFetch["select"]);
        try {
            $u = $this->createTableForInfo($settingsFetch);
            $searchFields = $u->getFields();
            if (!empty($patterns)) {
                $this->sortBy = key_exists($this->sortBy, $patterns) ?
                    $patterns[$this->sortBy] : $this->sortBy;
                foreach ($patterns as $key => $field)
                    $searchFields[$key] = array("Field" => $field, "Type" => "text");
            }
            if (!empty($this->search) || !empty($this->filters))
                $u->where($this->getWhereQuery($searchFields));
            $u->groupBy();
            $u->orderBy($this->sortBy, $this->sortOrder == 'desc');
            $this->result["items"] = $this->correctValuesBeforeFetch($u->getList($this->limit, $this->offset));
            $this->result["count"] = $u->getListCount();
            if (!empty($settingsFetch["aggregation"])) { 
                if (!empty($settingsFetch["aggregation"]["type"]))
                    $settingsFetch["aggregation"] = array($settingsFetch["aggregation"]);
                foreach ($settingsFetch["aggregation"] as $aggregation) {
                    $query = "{$aggregation["type"]}({$aggregation["field"]})";
                    $this->result[$aggregation["name"]] = $u->getListAggregation($query);
                }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список объектов!";
        }

        return $this->result["items"];
    }

    public function info($id = null)
    {
        $id = empty($id) ? $this->input["id"] : $id;
        $settingsInfo = $this->getSettingsInfo();
        try {
            $u = $this->createTableForInfo($settingsInfo);
            $this->result = $u->getInfo($id);
            if (!$this->result["id"]) {
                $this->error = "Объект с запрошенными данными не найден!";
                $this->statusAnswer = 404;
            } else {
                if ($addInfo = $this->getAddInfo()) {
                    $this->result = array_merge($this->result, $addInfo);
                }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию об объекте!";
        }
        return $this->result;
    }

    protected function getAddInfo()
    {
        return array();
    }

    public function delete()
    {
        try {
            if ($this->input["ids"] && !empty($this->tableName)) {
                $ids = implode(",", $this->input["ids"]);

                $u = new DB($this->tableName);
                $u->where('id IN (?)', $ids)->deleteList();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся произвести удаление!";
        }
    }

    public function save()
    {
        try {
            $this->correctValuesBeforeSave();
            DB::beginTransaction();
            $u = new DB($this->tableName);
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();
            if (empty($this->input["ids"]) && $this->input["id"])
                $this->input["ids"] = array($this->input["id"]);
            if ($this->input["id"] && $this->saveAddInfo()) {
                $this->info();
                DB::commit();
                return $this;
            } else throw new Exception();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить информацию об объекте!" : $this->error;
        }
    }

    public function sort()
    {
        if (empty($this->tableName))
            return;

        try {
            $sortIndexes = $this->input["indexes"];
            foreach ($sortIndexes as $index) {
                $u = new DB($this->tableName);
                $index["position"] = $index["sort"];                
                $u->setValuesFields($index);
                $u->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся произвести сортировку элементов!";
        }
    }

    protected function correctValuesBeforeSave()
    {
        return true;
    }

    protected function correctValuesBeforeFetch($items = array())
    {
        return $items;
    }

    protected function saveAddInfo()
    {
        return true;
    }

    protected function getSettingsFetch()
    {
        return array();
    }

    protected function getSettingsInfo()
    {
        return array();
    }

    protected function getPattensBySelect($selectQuery)
    {
        $result = array();
        preg_match_all('/\w+[.]+\w+\s\w+/', $selectQuery, $matches);
        if (count($matches) && count($matches[0])) {
            foreach ($matches[0] as $match) {
                $match = explode(" ", $match);
                if (count($match) == 2) {
                    $key = DB::strToCamelCase($match[1]);
                    $result[$key] = $match[0];
                }
            }
        }
        return $result;
    }

    protected function getSearchQuery($searchFields = array())
    {
        $result = array();
        $searchItem = $this->search;
        if (empty($searchItem))
            return $result;

        foreach ($searchFields as $field) {
            if (strpos($field["Field"], ".") === false)
                $field["Field"] = $this->tableAlias . "." . $field["Field"];
            // текст
            if ((strpos($field["Type"], "char") !== false) || (strpos($field["Type"], "text") !== false)) {
                $result[] = "{$field["Field"]} LIKE '%{$searchItem}%'";
                continue;
            }
            // дата
            if ($field["Type"] == "date") {
                $searchItem = date("Y-m-d", strtotime($searchItem));
                $result[] = "{$field["Field"]} = '$searchItem'";
                continue;
            }
            // время
            if ($field["Type"] == "time") {
                $searchItem = date("H:m:s", strtotime($searchItem));
                $result[] = "{$field["Field"]} = '$searchItem'";
                continue;
            }
            $result[] = "{$field["Field"]} = '{$searchItem}'";
        }
        return implode(" OR ", $result);
    }

    protected function getFilterQuery()
    {
        $where = array();
        $filters = array();
        if (!empty($this->filters["field"]))
            $filters[] = $this->filters;
        else $filters = $this->filters;
        foreach ($filters as $filter) {
            if (key_exists($filter["field"], $this->patterns))
                $field = $this->patterns[$filter["field"]];
            else {
                $field = DB::strToUnderscore($filter["field"]);
                $field = $this->tableAlias . ".`{$field}`";
            }
            $sign = empty($filter["sign"]) || !in_array($filter["sign"], $this->availableSigns) ?
                "=" : $filter["sign"];
            if ($sign == "IN") {
                $values = explode(",", $filter["value"]);
                $filter['value'] = null;
                foreach ($values as $value) {
                    if ($filter['value'])
                        $filter['value'] .= ",";
                    $value = trim($value);
                    $filter['value'] .= "'{$value}'";
                }
                $value = "({$filter['value']})";
            } else $value = !isset($filter["value"]) ? null : "'{$filter['value']}'";
            if (!$field || !$value)
                continue;
            $where[] = "{$field} {$sign} {$value}";
        }
        return implode(" AND ", $where);
    }

    protected function getWhereQuery($searchFields = array())
    {
        $query = null;
        $searchQuery = $this->getSearchQuery($searchFields);
        $filterQuery = $this->getFilterQuery();
        if ($searchQuery)
            $query = $searchQuery;
        if ($filterQuery) {
            if (!empty($query))
                $query = "({$query}) AND ";
            $query .= $filterQuery;
        }
        return $query;
    }
 
}