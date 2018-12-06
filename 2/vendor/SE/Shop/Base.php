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
    private $currData;


    function __construct($input = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        parent::__construct($input);
        $cl = explode('\\', get_class($this));
        $this->fileSettings = strtolower(DIR_SETTINGS . "/" . end($cl)) . ".json";
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
        if (is_array($this->input))
            $this->input = $this->correctInput($this->input);
        $this->debugging('shop/base', __FUNCTION__.' '.__LINE__, __CLASS__, 'return',
            $array=array("this->result"=>$this->result, "this->input"=>$this->input));
    }

    public function setFilters($filters)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $this->filters = empty($filters) || !is_array($filters) ? [] : $filters;
    }

    private function createTableForInfo($settings)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $settingsFetch = $this->getSettingsFetch();

        $settingsFetch["select"] = $settingsFetch["select"] ? $settingsFetch["select"] : "*";
        $this->patterns = $this->getPattensBySelect($settingsFetch["select"]);
        try {
            $searchFields = [];
            $u = $this->createTableForInfo($settingsFetch); // начало запроса
            $fields = $u->getFields();
            foreach ($fields as $key => $field)
                if (empty($this->searchFields) || $this->isSearchField($key))
                    $searchFields[$key] = $field;
            if (!empty($this->patterns)) {
                $this->sortBy = key_exists($this->sortBy, $this->patterns) ?
                    $this->patterns[$this->sortBy] : $this->sortBy;
                foreach ($this->patterns as $key => $field) {
                    if (empty($this->searchFields) || $this->isSearchField($key))
                        $searchFields[$key] = array("Field" => $field, "Type" => "text");
                }
            }
            if (!empty($this->search) || !empty($this->filters))
                $u->where($this->getWhereQuery($searchFields));
            $u->groupBy();
            if (is_array($this->sortBy)) {
                foreach ($this->sortBy as $sortField)
                    $u->addOrderBy($sortField, $this->sortOrder == 'desc');

            } else $u->orderBy($this->sortBy, $this->sortOrder == 'desc');

            $this->result["searchFields"] = $this->searchFields;
            $this->result["items"] = $this->correctItemsBeforeFetch($u->getList($this->limit, $this->offset));

            $this->dataCurrencies($settingsFetch);

            if (!empty($settingsFetch["aggregation"]["type"]))
                $settingsFetch["aggregation"] = array($settingsFetch["aggregation"]);

            /** Формирование итогов таблицы
             * 1 получаем список столбцов для проверки наличия curr
             * 2 если валюта есть - формируем запрос с сортировкой по валюте | нет - без сортировки
             */

            $colProdigious = DB::query("
                 SELECT COLUMN_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = '{$this->tableName}'
                 ORDER BY ORDINAL_POSITION
            ")->fetchAll(); // 1
            $col = array();
            foreach ($colProdigious as $keyCol => $valueCol) {
                $col[$valueCol[COLUMN_NAME]] = $valueCol[COLUMN_NAME];
            }

            if (!empty($col["curr"])) { // 2
                $settingsFetch["aggregation"][] = ["type" => "COUNT", "field" => "*", "name" => "count", "name2" => "curr"];
                $colCurr = TRUE;
            } else {
                $settingsFetch["aggregation"][] = ["type" => "COUNT", "field" => "*", "name" => "count"];
                $colCurr = FALSE;
            }
            $query = [];
            foreach ($settingsFetch["aggregation"] as $aggregation) {
                if (!empty($aggregation["name2"])) $name2 = ", `{$aggregation['name2']}`";
                else $name2 = "";
                $query[] = "{$aggregation["type"]}({$aggregation["field"]}) `{$aggregation["name"]}`{$name2}";
            }
            if (!empty($query)) {
                $query = implode(",", $query);
                $aggregations = $u->getListAggregation($query, $settingsFetch, $this->currData, $colCurr);
                foreach ($settingsFetch["aggregation"] as $aggregation) {
                    $this->result[$aggregation["name"]] = $aggregations[$aggregation["name"]];
                }
            }

            $this->result = $this->correctResultBeforeFetch($this->result);
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список объектов!";
        }

        $this->debugging('shop/base', __FUNCTION__.' '.__LINE__, __CLASS__, 'return',
            $array=array("this->result"=>$this->result, "input"=>$this->input));

        return $this->result["items"];
    }

    public function dataCurrencies($settingsFetch)
    {

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        /** ДАННЫЕ ПО ВАЛЮТАМ
         *   2 запрашиваем название,приставки/окончания валют
         *   3 и добавляем в эллементы массива соответственно
         *
         *   4 если нужно конвертировать: получаем базовую валюту
         *   5 если нужно конвертировать: запрашиваем курс и конвертируем столбцы по списку
         *
         *   7 назначаем валюту итого
         *
         * 6 костыль на случай если в таблице БД будет "currency" вместо "curr",
         *   или валюта будет вовсе отсутствовать - назначается валюта по умолчанию (при параметре convertingValues)
         *
         * @param array $this->result
         *   - данные таблицы
         * @param array $settingsFetch["convertingValues"]
         *   - определение потребности конвертации (если есть);
         *   - массив конвертируемых столбцов
         * @return array $this->result
         *
         */

        $this->debugging('shop/base', __FUNCTION__.' '.__LINE__, __CLASS__, 'start',
            $array=array("this->result"=>$this->result, "this->input"=>$this->input, 'settingsFetch'=>$settingsFetch));

        if ($settingsFetch["convertingValues"]) {                                  // 4
            if ($this->input['curr']) {                                            // если валюта страницы не по умолчанию
                $u = new DB('money_title', 'mt');
                $u->select('mt.name, mt.title, mt.name_front');
                $u->where("name = '?'", $this->input["curr"]);
                $this->currData = $u->fetchOne();
                unset($u);
            } else {
                $this->getCurrData();
            }
        } else {                                                                   // 2
            $u = new DB('money_title', 'mt');
            $u->select('mt.name name, mt.title title, mt.name_front nameFront, mt.name_flang curr');
            $currList = $u->getList();
            unset($u);
        }

        foreach ($this->result["items"] as $key => &$item) {
            if (!empty($item["currency"])) $item["curr"] = $item["currency"];      // 6
            elseif (empty($item["curr"])) $item["curr"] = $this->currData["name"];

            if ($settingsFetch["convertingValues"]) {                              // 5
                $course = DB::getCourse($this->currData["name"], $item["curr"]);
                foreach ($settingsFetch["convertingValues"] as $key => $i) {
                    $item[$i] = (float)str_replace(" ","",$item[$i]);
                    $item[$i] = round($item[$i] * $course, 2);
                }
                if ($this->currData["name"])      $item["curr"] = $this->currData["name"];
                if ($this->currData["title"])     $item["titleCurr"] = $this->currData["title"];
                if ($this->currData["nameFront"]) $item["nameFront"] = $this->currData["nameFront"];
            } else { // 3
                foreach ($currList as $currUnit) {
                    if ($item["curr"] == $currUnit["name"]) {
                        $this->result["items"][$key]["titleCurr"] = $currUnit["title"];
                        $this->result["items"][$key]["nameFront"] = $currUnit["nameFront"];
                        $this->result["items"][$key]["curr"] = $currUnit["curr"];
                    };
                }
            }
        };

        $this->result["currTotal"] = array ( // 7
            "curr"      => $this->currData["name"],
            "titleCurr" => $this->currData["title"],
            "nameFront" => $this->currData["nameFront"]
        );
    }

    public function getCurrData() {
        $u = new DB('main', 'm');
        $u->select('mt.name, mt.title, mt.name_front');
        $u->innerJoin('money_title mt', 'm.basecurr = mt.name');
        $this->currData = $u->fetchOne();
        unset($u);
    }

    public function correctAll()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (!empty($this->input['allMode'])) {
            $this->allMode = true;
            $this->limit = 100000;
            $this->setFilters($this->input['allModeLastParams']['filters']);
            $this->search = $this->input['allModeLastParams']['searchText'];

            $result = $this->fetch();
            if ($result) {
                $ids = array();
                foreach ($result as $item) {
                    array_push($ids, $item['id']);
                }
                if (!empty($ids)) {
                    unset($result);
                }
                $this->input['ids'] = $ids;
            } else
                return $this->error = "Не выбрано ни одной записи!";
        }
        return true;
    }

    public function info($id = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->result['pageCurr'] = $this->pageCurr($this->result["curr"]);
        return $this->result;
    }

    private function pageCurr($name)
    {
        $u = new DB('money_title', 'mt');
        $u->select('mt.name, mt.title, mt.name_front');
        $u->where("name = '?'", $name);
        $return = $u->fetchOne();
        unset($u);
        return $return;
    } // получить данные по валюте страницы

    protected function getAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return [];
    }

    public function delete()
    {
        /**
         * 1 удаление элементов из основной таблицы
         * 2 удаление элементов из зависимых таблиц
         *
         * @param  string $this->tableName       имя таблицы           exm:"shop_price"
         * @param  string $this->tableAlias      псевдоним таблицы     exm:"sp"
         * @param  array  $this->input["ids"]    массив id на удаление exm:"array(0=>425, 1=>11)"
         * @param  array  $this->tableNameDepen  имена таблиц и поля соотношения (id элемента)
         * @return bool                          при удалении - TRUE
         */

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {

            // 1
            $this->correctAll('del');
            $u = new DB($this->tableName, $this->tableAlias);
            if ($this->input["ids"] && !empty($this->tableName)) {
                $ids = implode(",", $this->input["ids"]);
                $u->where('id IN (?)', $ids)->deleteList();
            }
            unset($u);

            // 2
            if ($this->tableNameDepen) {
                foreach ($this->tableNameDepen as $tabNameDepen => $field) {

                    if ($this->input["ids"] && !empty($tabNameDepen) && !is_array($field)) {

                        $u = new DB($tabNameDepen, $tabNameDepen);
                        $ids = implode(",", $this->input["ids"]);
                        $u->where($field.' IN (?)', $ids)->deleteList();
                        unset($u);

                    } elseif ($this->input["ids"] && !empty($tabNameDepen) && is_array($field)) {

                        $ids          = implode(",", $this->input["ids"]);
                        $initialField = $field["field"];
                        $intTab       = $field["intermediaryTable"];
                        $intFie       = $field["intermediaryField"];

                        DB::query("
                            DELETE $tabNameDepen
                            FROM `$tabNameDepen`
                            INNER JOIN $intTab t2 ON t2.id = $tabNameDepen.$initialField
                            WHERE t2.$intFie IN ($ids);
                        ");

                    }
                }
            }

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся произвести удаление!";
        }
        return false;
    }

    public function save($isTransactionMode = true)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            $this->correctValuesBeforeSave();
            $this->correctAll();
            if ($isTransactionMode)
                DB::beginTransaction();
            $u = new DB($this->tableName);
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();
            if (empty($this->input["ids"]) && $this->input["id"])
                $this->input["ids"] = array($this->input["id"]);
            if ($this->input["id"] && $this->saveAddInfo()) {
                $this->info();
                if ($isTransactionMode)
                    DB::commit();
                $this->afterSave();
                return $this;
            } else throw new Exception();
        } catch (Exception $e) {
            if ($isTransactionMode)
                DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить информацию об объекте!" : $this->error;
        }
    }

    public function sort()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return true;
    }

    protected function correctItemsBeforeFetch($items = [])
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return $items;
    }

    protected function saveAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return true;
    }

    protected function getSettingsFetch()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return [];
    }

    protected function getSettingsInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        return $this->getSettingsFetch();
    }

    protected function getPattensBySelect($selectQuery)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

        foreach ($this->searchFields as $searchField) {
            if ($searchField["active"] && !empty($searchField["query"]))
                $result[$searchField["field"]] = $searchField["query"];
        }

        return $result;
    }

    protected function getSearchQuery($searchFields = array())
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $searchItem = trim($this->search);
        if (empty($searchItem))
            return array();
        $where = array();
        $searchWords = explode(' ', $searchItem);
        foreach ($searchWords as $searchItem) {
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
                    if (!is_array($field["Field"]))
                        $field["Field"] = [$field["Field"]];
                    foreach ($field["Field"] as $fieldName)
                        $result[] = "{$fieldName} LIKE '%{$searchItem}%'";
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $where = [];
        $filters = [];
        if (!empty($this->filters["field"]))
            $filters[] = $this->filters;
        else $filters = $this->filters;
        foreach ($filters as $filter) {
            if (preg_match('/(0[1-9]|[12][0-9]|3[01])[.](0[1-9]|1[012])[.](19|20)\d\d/', $filter["value"]))
                $filter["value"] = date("Y-m-d", strtotime($filter["value"]));
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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

    public function post($tempFile = FALSE)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $countFiles = count($_FILES);
        $ups = 0;
        $items = [];
        if ($tempFile == true) $dir = DOCUMENT_ROOT . "/files/tempfiles";
        else $dir = DOCUMENT_ROOT . "/files";
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if ($this->input['send']) {
            if ($codeMail) {
                try {
                    $urlSendEmail = 'http://' . HOSTNAME . '/upload/sendmailorder.php';
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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

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
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
    }

    public function store()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if ($this->input["searchFields"])
            $this->uiSettings["searchFields"] = $this->input["searchFields"];

        $data = json_encode($this->uiSettings);
        file_put_contents($this->fileSettings, $data);
    }

    private function isSearchField($key)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach ($this->searchFields as $field) {
            if ($field["active"] && $field["field"] == $key)
                return true;
        }
        return false;
    }

    protected function correctResultBeforeFetch($result)
    {
        return $result;
    }

    private function correctInput($input)
    {
        $url = $this->protocol . "://" . $this->hostname;

        $tagImages = 'src="' . $url . '/images';

        $input["text"] = str_replace($tagImages, 'src="/images',  $input["text"]);
        $input["note"] = str_replace($tagImages, 'src="/images',  $input["note"]);
        $input["description"] = str_replace($tagImages, 'src="/images',  $input["description"]);

        return $input;
    }

    /*
     * @@@@@@  @@@@@@ @@     @@@@@@ @@@@@@@@ @@@@@@ |
     * @@   @@ @@     @@     @@        @@    @@     |
     * @@   @@ @@@@@@ @@     @@@@@@    @@    @@@@@@ |
     * @@   @@ @@     @@     @@        @@    @@     |
     * @@@@@@  @@@@@@ @@@@@@ @@@@@@    @@    @@@@@@ |
     *
     * Удаление всего содержимого директории
     */
    public function rmdir_recursive($dir)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach(scandir($dir) as $file) {
            if ('.' === $file || '..' === $file) continue;
            if (is_dir("$dir/$file")) $this->rmdir_recursive("$dir/$file");
            else unlink("$dir/$file");
        }
        rmdir($dir);
    }


    /*
     * @@@@@@@@ @@@@@@ @@     @@ @@@@@@ |
     *    @@    @@     @@@   @@@ @@  @@ |
     *    @@    @@@@@@ @@ @@@ @@ @@@@@@ |
     *    @@    @@     @@  @  @@ @@     |
     *    @@    @@@@@@ @@     @@ @@     |
     *
     * методы по работе с временными файлами
     */

    /*
     * запись во временные файлы
     * метод адаптирован к циклическим записям (для больших объемов данных)
     * создаются временные файлы в директории "files/tempfiles/" с именами "tempfile1.TMP" (число - номер цикла)
     */
    public function writTempFiles($writer, $cycleNum)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!empty($writer)) {
            $filename = DOCUMENT_ROOT . "/files/tempfiles/tempfile{$cycleNum}.TMP";
            $file     = fopen($filename, "w");
            fwrite($file, json_encode($writer));
            fclose($file);
        }
    }

    // чтение из временного файла
    public function readTempFiles($cycleNum)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $filename = DOCUMENT_ROOT . "/files/tempfiles/tempfile{$cycleNum}.TMP";
        $read   = json_decode(file_get_contents($filename)); // чтение файла
        return $read;
    }

    /**
     * Запуск Python 2.7 методов (ЭКСПЕРЕМЕНТАЛЬНЫЙ)
     *
     * $data = array('as', 'df', 'gh');
     * $resultData = $this->pyMethods("PyMethods.py", "test", $data);
     *
     * @param  str   $nameFile   имя файла .py (в директории запуска метода)
     * @param  str   $nameMethod имя метода Py
     * @param        $dataArray  данные для обработки скриптом (любой тип данных)
     * @return       $resultData результаты обработки (любой тип данных)
     */
    public function pyMethods($nameFile, $nameMethod, $dataArray)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $param = array('method' => $nameMethod, 'data' => $dataArray);
        $result = shell_exec('python ' . __DIR__.'/'.$nameFile.' '.escapeshellarg(json_encode($param)));
        $resultData = json_decode($result, true);
        return $resultData;
    } // запуск Python 2.7 методов


}