<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;
use SE\Base as CustomBase;

class Base extends CustomBase
{
    protected $isTableMode = true;
    protected $limit = 100;
    protected $offset = 0;
    protected $sortBy = "id";
    protected $sortOrder = "desc";
    protected $availableFields;
    protected $filterFields;
    protected $search;
    protected $filters = [];
    protected $searchFields = [];
    protected $tableName;
    protected $tableAlias;
    protected $allowedSearch = true;
    protected $availableSigns = array("=", "<=", "<", ">", ">=", "IN", "<>");
    protected $isNew;
    protected $allMode = false;

    private $patterns = [];
    private $fileSettings;
    private $uiSettings;


    function __construct($input = null)
    {
        parent::__construct($input);
        $this->fileSettings = strtolower(DIR_SETTINGS . "/" . end(explode('\\', get_class($this)))) . ".json";
        $input = $this->input;
        $this->limit = $input["limit"] && $this->limit ? (int)$input["limit"] : $this->limit;
        $this->offset = $input["offset"] ? (int)$input["offset"] : $this->offset;
        $this->sortOrder = $input["sortOrder"] ? $input["sortOrder"] : $this->sortOrder;
        $this->sortBy = $input["sortBy"] ? $input["sortBy"] : $this->sortBy;
        $this->search = $input["searchText"] && $this->allowedSearch ? $input["searchText"] : null;
        $this->filters = empty($this->input["filters"]) || !is_array($this->input["filters"]) ?
            [] : $this->input["filters"];
        if (!empty($this->input["id"]) && empty($this->input["ids"]))
            $this->input["ids"] = array($this->input["id"]);
        $this->isNew = empty($this->input["id"]) && empty($this->input["ids"]);
        if (empty($this->tableAlias) && !empty($this->tableName)) {
            $worlds = explode("_", $this->tableName);
            foreach ($worlds as $world)
                $this->tableAlias .= $world[0];
        }
        if (file_exists($this->fileSettings)) {
            $data = file_get_contents($this->fileSettings);
            $uiSettings = json_decode($data, true);
            if (!empty($uiSettings["searchFields"]))
                $this->searchFields = $uiSettings["searchFields"];
        }
    }

    public function setFilters($filters)
    {
        $this->filters = empty($filters) || !is_array($filters) ? [] : $filters;
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
            $searchFields = [];
            $u = $this->createTableForInfo($settingsFetch);
            $fields = $u->getFields();
            foreach ($fields as $key => $field)
                if (empty($this->searchFields) || $this->isSearchField($key))
                    $searchFields[$key] = $field;
            if (!empty($this->patterns)) {
                $this->sortBy = key_exists($this->sortBy, $this->patterns) ?
                    $this->patterns[$this->sortBy] : $this->sortBy;
                foreach ($this->patterns as $key => $field) {
                    if (empty($this->searchFields) || in_array($key, $this->searchFields))
                        $searchFields[$key] = array("Field" => $field, "Type" => "text");
                }
            }
            if (!empty($this->search) || !empty($this->filters))
                $u->where($this->getWhereQuery($searchFields));
            $u->groupBy();
            $u->orderBy($this->sortBy, $this->sortOrder == 'desc');

            $this->result["searchFields"] = $this->searchFields;
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

     public function correctAll(){
        if(!empty($this->input['allMode'])){
            $this->allMode = true;
            $this->limit = 10000;
            $this->setFilters($this->input['allModeLastParams']['filters']);
            $this->search = $this->input['allModeLastParams']['searchText'];

            $result = $this->fetch();
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

    public function info($id = null)
    {
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

    protected function getAddInfo()
    {
        return [];
    }

    public function delete()
    {
        try {
            $this->correctAll('del');
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

    public function save()
    {
        try {
            $this->correctValuesBeforeSave();
            $this->correctAll();
            DB::beginTransaction();
            $u = new DB($this->tableName);
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();
            if (empty($this->input["ids"]) && $this->input["id"])
                $this->input["ids"] = array($this->input["id"]);
            if ($this->input["id"] && $this->saveAddInfo()) {
                $this->info();
                DB::commit();
                $this->afterSave();
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

    protected function correctValuesBeforeFetch($items = [])
    {
        return $items;
    }

    protected function saveAddInfo()
    {
        return true;
    }

    protected function getSettingsFetch()
    {
        return [];
    }

     protected function getSettingsInfo()
    {
        return [];
    }

    protected function getPattensBySelect($selectQuery)
    {
        $result = [];
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

            $time = 0;
            if (strpos($searchItem, "-") !== false) {
                $time = strtotime($searchItem);
            }

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

    protected function getFilterQuery()
    {
        $where = [];
        $filters = [];
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

    protected function getWhereQuery($searchFields = [])
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

    public function getArrayFromCsv($file, $csvSeparator = ";")
    {
        if (!file_exists($file))
            return null;

        $result = [];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $i = 0;
            $keys = [];
            while (($row = fgetcsv($handle, 10000, $csvSeparator)) !== FALSE) {
                if (!$i) {
                    foreach ($row as &$item)
                        $keys[] = iconv('CP1251', 'utf-8', $item);
                } else {
                    $object = [];
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

    public function post()
    {
        $countFiles = count($_FILES);
        $ups = 0;
        $items = [];
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

    public function sendMail($codeMail, $idOrder = false)
    {
        if ($this->input['send']) {
            if ($codeMail) {
                try {
                    $urlSendEmail = 'http://' .  HOSTNAME . '/upload/sendmailorder.php';
                    $params = array(
                        'lang' => 'rus',
                        'idorder' => (!$idOrder) ? $this->input['id'] : $idOrder,
                        'codemail' => $codeMail
                    );
                    $result = file_get_contents($urlSendEmail, false, stream_context_create(array(
                        'http' => array(
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => http_build_query($params)
                        )
                    )));
                } catch (Exception $e) {
                    $this->error = "Не удаётся отправить письмо!";
                    throw new Exception($this->error);
                }
            }
        }
    }

    public function postRequest($shorturl, $data)
    {
        $url = "http://" . HOSTNAME . "/" . $shorturl;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        return curl_exec($ch);
    }

    protected function afterSave()
    {

    }

    public function store()
    {
        if ($this->input["searchFields"])
            $this->uiSettings["searchFields"] = $this->input["searchFields"] ;

        $data = json_encode($this->uiSettings);
        file_put_contents($this->fileSettings, $data);
    }

    private function isSearchField($key)
    {
        foreach ($this->searchFields as $field) {
            if ($field["active"] && $field["field"] == $key)
                return true;
        }
        return false;
    }

}