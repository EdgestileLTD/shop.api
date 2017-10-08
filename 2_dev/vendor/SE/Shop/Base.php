<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;
use SE\Base as CustomBase;


class Base extends CustomBase
{
    // отладка
    function debugging($group,$funct,$act) {                // группа логв / функция / комент
        // значение:        True - печатать в логи /        False - не печатать

        $print = array(
            'funct'                                 => False,   // безымянные
            'данные по товару'                      => False
        );

        if($print[$group] == True) {
            $wrLog          = __FILE__;
            $Indentation    = str_repeat(" ", (100 - strlen($wrLog)));
            $wrLog          = "{$wrLog} {$Indentation}| Start function: {$funct}";
            $Indentation    = str_repeat(" ", (150 - strlen($wrLog)));
            writeLog("{$wrLog}{$Indentation} | Act: {$act}");
        }
    }


    protected $isTableMode = true;
    protected $limit = 100;
    protected $offset = 0;
    protected $sortBy = "id";
    protected $groupBy = "id";
    protected $sortOrder = "desc";
    protected $availableFields;
    protected $filterFields;
    protected $search;
    protected $filters = array();
    protected $tableName;
    protected $tableAlias;
    protected $allowedSearch = true;
    protected $availableSigns = array("=", "<=", "<", ">", ">=", "IN");
    protected $isNew;

    protected $allMode = false;
    protected $whereStr = null;
    protected $sqlFilter = null;

    private $patterns = array();

    // создание
    function __construct($input = null)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        parent::__construct($input);
        $input = $this->input;
        $this->limit = $input["limit"] && $this->limit ? (int)$input["limit"] : $this->limit;
        $this->offset = $input["offset"] ? (int)$input["offset"] : $this->offset;
        $this->sortOrder = $input["sortOrder"] ? $input["sortOrder"] : $this->sortOrder;
        $this->sortBy = $input["sortBy"] ? $input["sortBy"] : $this->sortBy;
        $this->search = $input["searchText"] && $this->allowedSearch ? $input["searchText"] : null;
        $this->filters = empty($this->input["filters"]) || !is_array($this->input["filters"]) ?
           array() : $this->input["filters"];
        if (!empty($this->input["id"]) && empty($this->input["ids"]))
            $this->input["ids"] = array($this->input["id"]);
        $this->isNew = empty($this->input["id"]) && empty($this->input["ids"]);
        if (empty($this->tableAlias) && !empty($this->tableName)) {
            $worlds = explode("_", $this->tableName);
            foreach ($worlds as $world)
                $this->tableAlias .= $world[0];
        }
    }

    // набор фильтров
    public function setFilters($filters)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $this->filters = empty($filters) || !is_array($filters) ? array() : $filters;
    }

    // Создать таблицу для информации
    private function createTableForInfo($settings)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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

    // получить
    public function fetch($isId = false)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        // Получить настройки Fetch
        $settingsFetch = $this->getSettingsFetch();

        $settingsFetch["select"] = $settingsFetch["select"] ? $settingsFetch["select"] : "*";
        if ($isId) {
            $settingsFetch["select"] = $this->tableAlias . '.id';
        }
        // Получить шаблоны по выбору
        $this->patterns = $this->getPattensBySelect($settingsFetch["select"]);
        try {
            // Создать таблицу для информации
            $u = $this->createTableForInfo($settingsFetch);
            // Получить поля
            $searchFields = $u->getFields();
            if (!empty($this->patterns)) {
                $this->sortBy = key_exists($this->sortBy, $this->patterns) ?
                    $this->patterns[$this->sortBy] : $this->sortBy;
                foreach ($this->patterns as $key => $field)
                    $searchFields[$key] = array("Field" => $field, "Type" => "text");
            }
            if (!empty($this->search) || !empty($this->filters))
                // получит запрос
                $u->where($this->getWhereQuery($searchFields));
            if ($this->groupBy)
                // группа по
                $u->groupBy($this->groupBy);
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

    // информация
    public function info($id = null)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $id = empty($id) ? $this->input["id"] : $id;
        $this->input["id"] = $id;
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

    // получить добавленную информацию
    protected function getAddInfo()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return array();
    }

    // удалить
    public function delete()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $this->correctAll('del');


        try {
            $u = new DB($this->tableName,$this->tableAlias);
            if ($this->input["ids"] && !empty($this->tableName)) {
                $ids = implode(",", $this->input["ids"]);
                $u->where('id IN (?)', $ids)->deleteList();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся произвести удаление!";
        }
        return false;
    }

    /**
     *  correctAll - корректировка запроса с клиента, при использованиии AllMode-режима
     *
     * @param string $action - тип запроса AllMode
     *          * del - режим удаления
     *          * null (default) - обычный режим
     * @return void
     */
    // исправить все
    public function correctAll($action = null){
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        if(isset($this->input['allMode'])){
            $this->allMode = true;
            // Сбрасываем лимиты
            $this->limit = 10000;
            // Устанавливаем фильтры
            $this->setFilters($this->input['allModeLastParams']['filters']);
            $this->search = $this->input['allModeLastParams']['searchText'];

            $result = $this->fetch(true);
            if($result){
                $ids = array();
                foreach ($result as $item){
                    array_push($ids,$item['id']);
                }
                if(!empty($ids)){unset($result);}
                $this->input['ids'] = $ids;
            } else
                return $this->error = "Не выбрано ни одной записи!";
        }
        return true;
    }

    // сохранить
    public function save()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка

        try {
            $this->correctValuesBeforeSave();
            $this->correctAll();
            $this->debugging('данные по товару',__FUNCTION__.' '.__LINE__,"данные по товару 240"); // отладка
            //writelog($this->input);
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

    // сортировать
    public function sort()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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

    // сохранить правильные значения
    protected function correctValuesBeforeSave()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return true;
    }

    // Правильные значения перед извлечением
    protected function correctValuesBeforeFetch($items = array())
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return $items;
    }

    // сохранить добавленную информацию
    protected function saveAddInfo()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return true;
    }

    // получить настройки
    protected function getSettingsFetch()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return array();
    }

    // найти настройки
    protected function getSettingsFind()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return array();
    }

    // получить информацию по настройкам
    protected function getSettingsInfo()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return array();
    }

    // Получить шаблоны по выбору
    protected function getPattensBySelect($selectQuery)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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

    // получить запрос на поиск
    protected function getSearchQuery($searchFields = array())
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $searchItem = trim($this->search);
        if (empty($searchItem))
            return array();
        $where = array();
        $searchWords = explode(' ', $searchItem);
        foreach($searchWords as $searchItem) {
            $result = array();
            if (!trim($searchItem)) continue;
            if (is_string($searchItem))
                $searchItem = trim(DB::quote($searchItem), "'");

            $finds = $this->getSettingsFind();
            $time = 0;
            if (strpos($searchItem, "-") !== false) {
                $time = strtotime($searchItem);
            }

            foreach ($searchFields as $field) {
                if (strpos($field["Field"], ".") === false)
                    $field["Field"] = $this->tableAlias . "." . $field["Field"];
                if (!empty($finds) && !in_array($field["Field"], $finds)) continue;

                // текст
                if ((strpos($field["Type"], "char") !== false) || (strpos($field["Type"], "text") !== false)) {
                    $result[] = "{$field["Field"]} LIKE '%{$searchItem}%'";
                    continue;
                }
                // дата
                if ($field["Type"] == "date") {
                    if ($time) {
                        $searchItem = date("Y-m-d", $time);
                        $result[] = "{$field["Field"]} = '$searchItem'";
                    }
                    continue;
                }
                // время
                if ($field["Type"] == "time") {
                    if ($time) {
                        $searchItem = date("H:i:s", $time);
                        $result[] = "{$field["Field"]} = '$searchItem'";
                    }
                    continue;
                }
                // дата и время
                if ($field["Type"] == "datetime") {
                    if ($time) {
                        $searchItem = date("Y-m-d H:i:s", $time);
                        $result[] = "{$field["Field"]} = '$searchItem'";
                    }
                    continue;
                }
                // число
                if (strpos($field["Type"], "int") !== false) {
                    if (intval($searchItem)) {
                        $result[] = "{$field["Field"]} = " . intval($searchItem);
                        continue;
                    }
                }
            }
            if (!empty($result))
                $where[] = '(' . implode(" OR ", $result) . ')';
        }
        return implode(" AND ", $where);
    }

    // получить запрос фильтра
    protected function getFilterQuery()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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

    // получить положение запроса
    protected function getWhereQuery($searchFields = array())
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
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


    // получить масив из Csv
    public function getArrayFromCsv($file, $csvSeparator = ";")
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        if (!file_exists($file))
            return null;

        $result = array();
        if (($handle = fopen($file, "r")) !== FALSE) {
            $i = 0;
            $keys = array();
            while (($row = fgetcsv($handle, 10000, $csvSeparator)) !== FALSE) {
                if (!$i) {
                    foreach ($row as &$item)
                        $keys[] = iconv('CP1251', 'utf-8', $item);
                } else {
                    $object = array();
                    $j = 0;
                    foreach ($row as &$item) {
                        $object[$keys[$j]] = iconv('CP1251', 'utf-8', $item);
                        $j++;
                    }
                    $result[] = $object;
                }
                $i++;
            }
            fclose($handle);
        }
        return $result;
    }

    // после
    public function post()
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $countFiles = count($_FILES);
        $ups = 0;
        $items = array();
        $dir = DOCUMENT_ROOT . "/files";
        $url = !empty($_POST["url"]) ? $_POST["url"] : null;
        if (!file_exists($dir) || !is_dir($dir))
            mkdir($dir);

        if ($url) {
            $content = file_get_contents($url);
            if (empty($content)) {
                $this->error = "Не удается загрузить данные по указанному URL!";
            } else {
                $items[] = array("url" => $url, "name" => array_pop(explode("/", $url)));
                $this->result['items'] = $items;
            }
        } else {
            for ($i = 0; $i < $countFiles; $i++) {
                $file = empty($_FILES["file"]) ? $_FILES["file$i"]['name'] : $_FILES["file"]['name'];
                $uploadFile = $dir . '/' . $file;
                $fileTemp = $_FILES["file$i"]['tmp_name'];
                $urlFile = 'http://' . HOSTNAME . "/files/{$file}";
                if (!filesize($fileTemp) || move_uploaded_file($fileTemp, $uploadFile)) {
                    $items[] = array("url" => $urlFile, "name" => $file);
                    $ups++;
                }
            }
            if ($ups == $countFiles)
                $this->result['items'] = $items;
            else $this->error = "Не удается загрузить файлы!";
        }

        return $items;
    }

    // почтовый запрос
    public function postRequest($shorturl, $data)
    {
        $this->debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $url = "http://" . HOSTNAME . "/" . $shorturl;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        return curl_exec($ch);
    }

}