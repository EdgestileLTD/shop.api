<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;
use SE\Shop\Import;
use SE\Shop\ProductExport as ProductExport;

//use \PHPExcel as PHPExcel;
//use \PHPExcel_Writer_Excel2007 as PHPExcel_Writer_Excel2007;
//use \PHPExcel_Style_Fill as PHPExcel_Style_Fill;

class Product extends Base
{
    /**
     * @var string $tableName имя основной таблмцы
     * @var array $tableNameDepen имена таблиц и поля соотношения (id товара)
     */
    protected $tableName      = "shop_price";
    protected $tableNameDepen = array(
        "shop_price_group"           => "id_price",
        "shop_modifications_feature" => "id_price"
    );
    private $newImages;

    // руссификация заголовков столбцов
    protected $rusCols = array(
        "id" => "Ид.", "article" => "Артикул", "code" => "Код (URL)", "name" => "Наименование",
        "price" => "Цена пр.", "pricePurchase" => "Цена закуп.", "priceOpt" => "Цена опт.", "priceOptCorp" => "Цена корп.", "bonus" => "Цена бал.",
        "count" => "Остаток",
        "category" => "Путь категории", "codeGroup" => "Код категории", "shopIdGroup" => "Ид. категории",
        "weight" => "Вес", "volume" => "Объем", "measurement" => "Ед.Изм.", "measuresWeight" => "Меры веса", "measuresVolume" => "Меры объема",
        "description" => "Краткое описание", "fullDescription" => "Полное описание", "stepCount" => "Шаг количества",
        "features" => "Характеристики",
        "images" => 'Изображения',
        "codeCurrency" => "КодВалюты",
        "metaHeader" => "MetaHeader", "metaKeywords" => "MetaKeywords", "metaDescription" => "MetaDescription",
        "flagNew" => "Новинки", "flagHit" => "Хиты", "enabled" => "Видимость", "isMarket" => "Маркет",
        "minCount" => "Мин.кол-во", "nameBrand" => "Бренд",
        "idAcc" => "Сопутствующие товары"
    );

    // поля для поиска
    protected $searchFields = [
        ["title" => "Код", "field" => "code"],
        ["title" => "Наименование", "field" => "name", "active" => true],
        ["title" => "Артикул", "field" => "article", "active" => true],
        ["title" => "Группа", "field" => "nameGroup"],
        ["title" => "Бренд", "field" => "nameBrand"]
    ];

    // @@@@@@ @@@@@@    @@    @@  @@ @@  @@     @@  @@    @@    @@@@@@ @@@@@@@@ @@@@@@ @@@@@@ @@    @@ @@  @@ @@    @@
    // @@  @@ @@  @@   @@@@   @@  @@ @@  @@     @@  @@   @@@@   @@        @@    @@  @@ @@  @@ @@   @@@ @@ @@  @@   @@@
    // @@  @@ @@  @@  @@  @@   @@@@  @@@@@@     @@@@@@  @@  @@  @@        @@    @@@@@@ @@  @@ @@  @@@@ @@@@   @@  @@@@
    // @@  @@ @@  @@ @@    @@   @@       @@     @@  @@ @@@@@@@@ @@        @@    @@     @@  @@ @@@@  @@ @@ @@  @@@@  @@
    // @@  @@ @@@@@@ @@    @@   @@       @@     @@  @@ @@    @@ @@@@@@    @@    @@     @@@@@@ @@@   @@ @@  @@ @@@   @@

    // Получить настройки
    protected function getSettingsFetch()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if ($_SESSION['coreVersion'] > 520) {
            // получаем данные из таблиц БД
            $select = 'sp.id, sp.id_group shop_id_group, sp.code, sp.article, sp.name,
                sp.price, sp.price_opt, sp.price_opt_corp,
                sp.img_alt, sp.curr, sp.presence, sp.bonus, sp.min_count,
                sp.presence_count presence_count, sp.special_offer, sp.flag_hit, sp.enabled, sp.flag_new, sp.is_market, sp.note, sp.text,
                sp.price_purchase price_purchase, sp.measure, sp.step_count, sp.max_discount, sp.discount,
                sp.title, sp.keywords, sp.description, sp.weight, sp.volume, spg.is_main,
                spg.id_group id_group, sg.name name_group, sg.id_modification_group_def id_modification_group_def,
                COUNT(DISTINCT(smf.id_modification)) count_modifications,
                (SELECT picture FROM shop_img WHERE id_price = sp.id LIMIT 1) img,
                sb.name name_brand, slp.id_label id_label, sp.is_show_feature, sp.market_available, 
                spm.id_weight_view, spm.id_weight_edit, spm.id_volume_view, spm.id_volume_edit';

            $joins[] = array(
                "type" => "left",
                "table" => 'shop_price_group spg',
                "condition" => '(spg.id_price = sp.id) AND (spg.is_main = true)'
            );

            $joins[] = array(
                "type" => "left",
                "table" => 'shop_group sg',
                "condition" => 'sg.id = sp.id_group'
            );
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_price_measure spm',
                "condition" => 'sp.id = spm.id_price'
            );
        } else {
            $select = 'sp.*, sg.name name_group, sg.id_modification_group_def id_modification_group_def,
                sb.name name_brand';
            $joins[] = array(
                "type" => "left",
                "table" => 'shop_group sg',
                "condition" => 'sg.id = sp.id_group'
            );
        }
        $joins[] = array(
            "type" => "left",
            "table" => 'shop_brand sb',
            "condition" => 'sb.id = sp.id_brand'
        );
        $joins[] = array(
            "type" => "left",
            "table" => 'shop_group_price sgp',
            "condition" => 'sp.id = sgp.price_id'
        );
        $joins[] = array(
            "type" => "left",
            "table" => 'shop_label_product slp',
            "condition" => 'sp.id = slp.id_product'
        );

        $joins[] = array(
            "type" => "left",
            "table" => '(SELECT smf.id_price, smf.id_modification FROM shop_modifications_feature smf
                           WHERE NOT smf.id_value IS NULL AND NOT smf.id_modification IS NULL GROUP BY smf.id_modification) smf',
            "condition" => 'sp.id = smf.id_price'
        );

        $convertingValues[] = array(
            "price",
            "priceOpt",
            "priceOptCorp",
            "pricePurchase"
        );

        $result["select"] = $select;
        $result["joins"] = $joins;
        $result["convertingValues"] = $convertingValues[0];
        return $result;
    }

    // Получить
    public function fetch($isId = false)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        parent::fetch($isId);
        if (!$isId) {
            $list = $this->result['items'];
            $this->result['items'] = array();
            foreach ($list as $item) {
                if (strpos($item['img'], "://") === false) {
                    if ($item['img'] && file_exists(DOCUMENT_ROOT . '/images/rus/shopprice/' . $item['img']))
                        $item['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/shopprice/" . $item['img'];
                } else {
                    $item['imageUrlPreview'] = $item['img'];
                }
                $this->result['items'][] = $item;
            }
        }
        return $this->result["items"];
    }

    // Добавить изменения
    public function addModifications($ids)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $array = $result = array();
        $searchBase = array(
            'values' => array(),
            'group' => array(),
            'items' => array()
        );
        foreach ($ids as $id) {

            $array[$id] = $this->getModifications($id);
            // Если у товара нет модификаций то отправляем пустое значение
            if (empty($array[$id])) return $this->result['modifications'] = array();

            // Собираем информацию о схожих группах
            foreach ($array[$id] as $group) {
                $searchBase['group'][$id][] = $group['id'];
                foreach ($group['items'] as $item)
                    $searchBase['items'][$id][$group['id']][] = $this->diffArray($item['values'], true);
            }
        }
        // Проверка групп
        $tmp = array_shift($searchBase['group']);
        foreach ($searchBase['group'] as $gr) {
            $tmp = array_intersect($tmp, $gr);
        }
        $searchBase['group'] = $tmp;

        $i = 0;
        // Проверка элементов групп
        foreach ($searchBase['group'] as $gid) {
            foreach ($searchBase['items'] as $arrayItem) {
                if (!is_array($searchBase['values'][$gid])) {
                    $searchBase['values'][$gid] = array();
                    $i = $gid;
                }
                $searchBase['values'][$gid][] = $arrayItem[$gid];
            }
        }

        foreach ($searchBase['group'] as $gid) {
            $tmp = false;
            $first = true;
            foreach ($searchBase['values'][$gid] as $val) {
                if (!is_array($tmp)) {
                    if ($first == false) {
                        $tmp = array();
                        break 2;
                    }
                    $tmp = $val;
                    $first = false;
                } else {
                    $tmp = array_intersect($tmp, $val);
                }
            }
            $searchBase['values'][$gid] = $tmp;
        }

        if (!empty($searchBase['values'])) {
            $result = array_shift($array);
            foreach ($result as $indexG => $group) {
                if (in_array($group['id'], $searchBase['group'])) {
                    foreach ($group['items'] as $indexI => $item) {
                        $needle = $this->diffArray($item['values'], true);
                        if (!in_array($needle, $searchBase['values'][$group['id']])) unset($result[$indexG]['items'][$indexI]);
                    }
                } else unset($result[$indexG]);
            }
        }

        return $this->result['modifications'] = array_values($result);
    }


    // @@    @@ @@  @@ @@@@@@@@@ @@@@@@
    // @@   @@@ @@  @@ @@  @  @@ @@  @@
    // @@  @@@@ @@@@@@ @@  @  @@ @@  @@
    // @@@@  @@ @@  @@ @@@ @ @@@ @@  @@
    // @@@   @@ @@  @@     @     @@@@@@

    // Инфо
    public function info($id = NULL)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        // исправить все
        $this->correctAll();
        if (isset($this->input['action']) and $this->input['action'] == 'addModifications') {
            return $this->addModifications($this->input['ids']);
        }
        if (isset($this->input['set']) and is_array($this->input['id']) and count($this->input['id']) > 1) {
            $id_array = $this->input['id'];
            foreach ($id_array as $id) {
                if (!is_numeric($id)) {
                    return false;
                }
            }

            return $this->result = $this->getDiffFeatures($id_array, true);
        }
        parent::info(array_shift($this->input['id']));
        $meas = new Measure();
        $measure = $meas->info();
        $this->result['weightEdit'] = $this->result['weight'];
        $this->result['volumeEdit'] = $this->result['volume'];
        foreach ($measure["weights"] as $w) {
            if (($this->result['idWeightEdit'] == $w['id']) || empty($this->result['idWeightEdit'])) {
                if (empty($this->result['idWeightEdit']))
                    $this->result['idWeightEdit'] = $w["id"];
                $this->result['weightEdit'] = $this->result['weight'] * $w['value'];
                break;
            }
        }
        foreach ($measure["volumes"] as $v) {
            if (($this->result['idVolumeEdit'] == $v['id']) || empty($this->result['idVolumeEdit'])) {
                if (empty($this->result['idVolumeEdit']))
                    $this->result['idVolumeEdit'] = $v["id"];
                $this->result['volumeEdit'] = $this->result['volume'] * $v['value'];
                break;
            }
        }

    }

    private function calkMeasure($table, $id)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_measure');
    }

    // Получить функции Diff
    private function getDiffFeatures($id_array, $retard = FALSE)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (count($id_array) < 2) {
            return array();
        }
        $id = array_shift($id_array);
        $ids = implode(',', $id_array);
        $sql = 'SELECT `id` FROM `shop_modifications_feature` WHERE `id_price` = %d AND `id_value` IN (SELECT `id_value` FROM `shop_modifications_feature` WHERE `id_price` IN (%s))';
        $sql = sprintf($sql, $id, $ids);
        $result = DB::query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        $return = array();
        foreach ($this->getSpecifications($id) as $items) {
            foreach ($result as $item) {
                if ($retard) {
                    if ($item['id'] == $items['id']) {
                        $return[] = $items;
                    }
                } else {
                    if ($item['id'] == $items['id']) {
                        $return[] = array(
                            'id_feature' => $items['idFeature'],
                            'id_value' => $items['idValue']
                        );
                    }
                }
            }
        }
        return $return;
    }

    // Получить настройки
    protected function getSettingsInfo()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        return $this->getSettingsFetch();
    }

    // Получить изображения
    public function getImages($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_img', 'si');
        $u->where('si.id_price = ?', $id);
        $u->orderBy("sort");
        $objects = $u->getList();

        foreach ($objects as $item) {
            $image = null;
            $image['id'] = $item['id'];
            $image['imageFile'] = $item['picture'];
            $image['imageAlt'] = $item['pictureAlt'];
            $image['sortIndex'] = $item['sort'];
            $image['isMain'] = (bool)$item['default'];
            if ($image['imageFile']) {
                if (strpos($image['imageFile'], "://") === false) {
                    $image['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shopprice/" . $image['imageFile'];
                    $image['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shopprice/" . $image['imageFile'];
                } else {
                    $image['imageUrl'] = $image['imageFile'];
                    $image['imageUrlPreview'] = $image['imageFile'];
                }
            }
            if (empty($product["imageFile"]) && $image['isMain']) {
                $product["imageFile"] = $image['imageFile'];
                $product["imageAlt"] = $image['imageAlt'];
            }
            $result[] = $image;
        }
        return $result;
    }

    // Получить файлы
    public function getFiles($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_files', 'si');
        $u->addField('sort', 'int(11)', '0', 1);
        $u->where('si.id_price = ?', $id);
        $u->orderBy("sort");
        $objects = $u->getList();

        foreach ($objects as $item) {
            $file = null;
            $file['id'] = $item['id'];
            $file['fileURL'] = $item['file'];
            $file['fileText'] = $item['name'];
            $file['fileName'] = basename($item['file']);
            $file['fileExt'] = strtoupper(substr(strrchr($item['file'], '.'), 1));
            $file['sortIndex'] = $item['sort'];
            if ($file['fileUrl']) {
                if (strpos($file['fileUrl'], "://") === false) {
                    $file['fileUrl'] = 'http://' . HOSTNAME . "/files/" . $item['file'];
                }
            }
            $result[] = $file;
        }
        return $result;
    }

    // Добавить цену
    public function addPrice()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $this->correctAll();
        try {
            $idsProducts = $this->input["ids"];
            $idsStr = implode(",", $idsProducts);
            $source = $this->input["source"];
            $type = $this->input["value"];
            $price = $this->input["price"];
            $sql = "UPDATE shop_price SET price = ";
            $priceField = $source ? "price_purchase" : "price";
            if ($type == "a")
                $sql .= "{$priceField}+" . $price;
            if ($type == "p")
                $sql .= "{$priceField}+{$priceField}*" . $price / 100;
            $sql .= " WHERE id IN ({$idsStr})";
            DB::query($sql);

            $sqlMod = "UPDATE shop_modifications sm 
                INNER JOIN shop_modifications_group smg ON sm.id_mod_group = smg.id SET `value` = ";
            if ($type == "a")
                $sqlMod .= "`value` + " . $price;
            if ($type == "p")
                $sqlMod .= "`value` + `value` * " . $price / 100;
            $sqlMod .= " WHERE id_price IN ({$idsStr}) AND smg.vtype = 2";
            DB::query($sqlMod);

        } catch (Exception $e) {
            $this->error = "Не удаётся произвести наценку выбранных товаров!";
        }
    }

    // Получить характеристики товара
    public function getSpecifications($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        try {
            $u = new DB('shop_modifications_feature', 'smf');
            $u->select('sfg.id id_group, sfg.name group_name, sf.name,
						sf.type, sf.measure, smf.*, sfvl.value, sfvl.color, sfg.sort index_group');
            $u->innerJoin('shop_feature sf', 'sf.id = smf.id_feature');
            $u->leftJoin('shop_feature_value_list sfvl', 'smf.id_value = sfvl.id');
            $u->leftJoin('shop_feature_group sfg', 'sfg.id = sf.id_feature_group');
            $u->where('smf.id_price = ? AND smf.id_modification IS NULL', $id);
            $u->orderBy('sfg.sort');
            $u->addOrderBy('sf.sort');
            $items = $u->getList();
            $result = [];
            foreach ($items as $item) {
                if ($item["type"] == "number")
                    $item["value"] = (real)$item["valueNumber"];
                elseif ($item["type"] == "string")
                    $item["value"] = $item["valueString"];
                elseif ($item["type"] == "bool")
                    $item["value"] = (bool)$item["valueBool"];
                $result[] = $item;
            }
            return $result;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить характеристики товара!";
        }
    }

    // Получить похожие продукты
    public function getSimilarProducts($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_sameprice', 'ss');
        $u->select('sp1.id id1, sp1.name name1, sp1.code code1, sp1.article article1, sp1.price price1,
                    sp2.id id2, sp2.name name2, sp2.code code2, sp2.article article2, sp2.price price2');
        $u->innerJoin('shop_price sp1', 'sp1.id = ss.id_price');
        $u->innerJoin('shop_price sp2', 'sp2.id = ss.id_acc');
        $u->where('sp1.id = ? OR sp2.id = ?', $id);
        $objects = $u->getList();
        foreach ($objects as $item) {
            $similar = null;
            $i = 1;
            if ($item['id1'] == $id)
                $i = 2;
            $similar['id'] = $item['id' . $i];
            $similar['name'] = $item['name' . $i];
            $similar['code'] = $item['code' . $i];
            $similar['article'] = $item['article' . $i];
            $similar['price'] = (real)$item['price' . $i];
            $result[] = $similar;
        }
        return $result;
    }

    // Получить сопроводительные продукты
    public function getAccompanyingProducts($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_accomp', 'sa');
        $u->select('sp.id, sp.name, sa.id_group, sg.name `group`, sp.code, sp.article, sp.price');
        $u->leftJoin('shop_price sp', 'sp.id = sa.id_acc');
        $u->leftJoin('shop_group sg', 'sg.id = sa.id_group');
        $u->where('sa.id_price = ?', $id);
        $u->orderBy("sa.id");
        $objects = $u->getList();
        foreach ($objects as $item) {
            $accompanying = null;
            $accompanying['id'] = $item['id'];
            $accompanying['idGroup'] = $item['idGroup'];
            $accompanying['name'] = $item['name'];
            $accompanying["type"] = "Товар";
            if ($item["group"]) {
                $accompanying["name"] = $item["group"];
                $accompanying["type"] = "Группа";
            }
            $accompanying['code'] = $item['code'];
            $accompanying['article'] = $item['article'];
            $accompanying['price'] = (real)$item['price'];
            $result[] = $accompanying;
        }
        return $result;
    }

    // Получить комментарии
    public function getComments($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $id = $idProduct ? $idProduct : $this->input["id"];
        $comment = new Comment();
        return $comment->fetchByIdProduct($id);
    }

    // Получить обзоры
    public function getReviews($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $id = $idProduct ? $idProduct : $this->input["id"];
        $review = new Review();
        return $review->fetchByIdProduct($id);
    }

    // Получить перекрестные группы
    public function getCrossGroups($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        if ($_SESSION['coreVersion'] > 520) {
            $u = new DB('shop_price_group', 'spg');
            $u->select('sg.id, sg.name');
            $u->innerJoin('shop_group sg', 'sg.id = spg.id_group');
            $u->where('spg.id_price = ? AND NOT spg.is_main', $id);
        } else {
            $u = new DB('shop_group_price', 'sgp');
            $u->select('sg.id, sg.name');
            $u->innerJoin('shop_group sg', 'sg.id = sgp.group_id');
            $u->where('sgp.price_id = ?', $id);
        }
        return $u->getList();
    }

    // Получить изменения (отображение товаров в разделе "товары")
    public function getModifications($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $newTypes = array("string" => "S", "number" => "D", "bool" => "B", "list" => "L", "colorlist" => "CL");
        $product = [];

        $u = new DB('shop_modifications', 'sm');
        $u->select('smg.*,
                GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sf.name, sf.`type`, sf.sort)) SEPARATOR "\n") `columns`');
        $u->innerJoin('shop_modifications_group smg', 'smg.id = sm.id_mod_group');
        $u->innerJoin('shop_modifications_feature smf', 'smf.id_modification = sm.id');
        $u->innerJoin('shop_feature sf', 'sf.id = smf.id_feature');
        $u->where('sm.id_price = ?', $id);
        $u->groupBy('smg.id');
        $u->orderBy('smg.sort');
        $objects = $u->getList();
        $isDefModification = false;
        if (empty($objects)) {
            $idGroup = $this->result["idModificationGroupDef"];
            if (empty($idGroup))
                return $result;

            $isDefModification = true;
            $u = new DB('shop_modifications_group', 'smg');
            $u->select('smg.*,
                GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sf.name, sf.`type`, sf.sort)) SEPARATOR "\n") `columns`');
            $u->innerJoin('shop_group_feature sgf', 'smg.id = sgf.id_group');
            $u->innerJoin('shop_feature sf', 'sf.id = sgf.id_feature');
            $u->where('smg.id = ?', $idGroup);
            $u->groupBy('smg.id');
            $u->orderBy('smg.sort');
            $objects = $u->getList();
        }
        foreach ($objects as $item) {
            $group = null;
            $group['id'] = $item['id'];
            $group['name'] = $item['name'];
            $group['sortIndex'] = $item['sort'];
            $group['type'] = $item['vtype'];
            if (!$product["idGroupModification"]) {
                $product["idGroupModification"] = $group['id'];
                $product["nameGroupModification"] = $group['name'];
            }
            $items = explode("\n", $item['columns']);
            foreach ($items as $item) {
                $item = explode("\t", $item);
                $column['id'] = $item[0];
                $column['name'] = $item[1];
                $column['type'] = $item[2];
                $column['sortIndex'] = $item[3];
                $column['valueType'] = $newTypes[$column['type']];
                $group['columns'][] = $column;
            }
            //$group['items'] = [];
            $groups[] = $group;
        }
        if (!isset($groups))
            return $result;
        if ($isDefModification)
            return $groups;

        $u = new DB('shop_modifications', 'sm');
        $u->select('sm.*,
                SUBSTRING(GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sfvl.id_feature, sfvl.id, sfvl.value, sfvl.sort, sfvl.color)) SEPARATOR "\n"), 1) values_feature,
                SUBSTRING(GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", smi.id_img, smi.sort, si.picture)) SEPARATOR "\n"), 1) images');
        $u->innerJoin('shop_modifications_feature smf', 'sm.id = smf.id_modification');
        $u->innerJoin('shop_feature_value_list sfvl', 'sfvl.id = smf.id_value');
        $u->leftJoin('shop_modifications_img smi', 'sm.id = smi.id_modification');
        $u->leftJoin('shop_img si', 'smi.id_img = si.id');
        $u->where('sm.id_price = ?', $id);
        $u->groupBy();
        $objects = $u->getList();
        $existFeatures = [];
        foreach ($objects as $item) {
            if ($item['id']) {
                $modification = null;
                $modification['id'] = $item['id'];
                $modification['article'] = $item['code'];
                if ($item['count'] != null)
                    $modification['count'] = (real)$item['count'];
                else $modification['count'] = -1;
                if (!$modification['article'])
                    $modification['article'] = $product["article"];
                if (!$modification['measurement'])
                    $modification['measurement'] = $product['measurement'];
                if (!$modification['measuresWeight'])
                    $modification['measuresWeight'] = $product['measuresWeight'];
                if (!$modification['measuresVolume'])
                    $modification['measuresVolume'] = $product['measuresVolume'];
                $modification['priceRetail'] = (real)$item['value'];
                $modification['priceSmallOpt'] = (real)$item['valueOpt'];
                $modification['priceOpt'] = (real)$item['valueOptCorp'];
                $modification['description'] = $item['description'];
                $features = explode("\n", $item['valuesFeature']);
                $sorts = [];
                foreach ($features as $feature) {
                    $feature = explode("\t", $feature);
                    $value = null;
                    $value['idFeature'] = $feature[0];
                    $value['id'] = $feature[1];
                    $value['value'] = $feature[2];
                    $sorts[] = $feature[3];
                    $value['color'] = $feature[4];
                    $modification['values'][] = $value;
                }
                $modification['sortValue'] = $sorts;
                if ($item['images']) {
                    $images = explode("\n", $item['images']);
                    foreach ($images as $image) {
                        $feature = explode("\t", $image);
                        $value = null;
                        $value['id'] = $feature[0];
                        $value['sortIndex'] = $feature[1];
                        $value['imageFile'] = $feature[2];
                        if ($value['imageFile']) {
                            if (strpos($value['imageFile'], "://") === false) {
                                $value['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shopprice/" . $value['imageFile'];
                                $value['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shopprice/" . $value['imageFile'];
                            } else {
                                $value['imageUrl'] = $image['imageFile'];
                                $value['imageUrlPreview'] = $image['imageFile'];
                            }
                        }
                        $modification['images'][] = $value;
                    }
                }
                foreach ($groups as &$group) {
                    if ($group['id'] == $item['idModGroup']) {
                        $group['items'][] = $modification;
                    }
                }
                $existFeatures[] = $item['valuesFeature'];
            }
        }
        return $groups;
    }


    // @@@@@@ @@@@@@    @@    @@  @@ @@  @@     @@@@@@ @@  @@ @@    @@ @@@@@@  @@  @@ @@    @@
    // @@  @@ @@  @@   @@@@   @@  @@ @@  @@     @@     @@ @@  @@   @@@ @@   @@ @@ @@  @@   @@@
    // @@  @@ @@  @@  @@  @@   @@@@  @@@@@@     @@     @@@@   @@  @@@@ @@   @@ @@@@   @@  @@@@
    // @@  @@ @@  @@ @@    @@   @@       @@     @@     @@ @@  @@@@  @@ @@   @@ @@ @@  @@@@  @@
    // @@  @@ @@@@@@ @@    @@   @@       @@     @@@@@@ @@  @@ @@@   @@ @@@@@@  @@  @@ @@@   @@

    // Получить скидки
    public function getDiscounts($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        $u = new DB('shop_discounts', 'sd');
        $u->select('sd.*');
        $u->innerJoin('shop_discount_links sdl', 'sdl.discount_id = sd.id');
        $u->where('sdl.id_price = ?', $id);
        $u->orderBy('sd.id');
        return $u->getList();
    }

    // @@@@@@     @@@@@@  @@@@@@ @@@@@@     @@    @@ @@  @@ @@@@@@@@@ @@  @@
    // @@  @@     @@   @@ @@  @@ @@  @@     @@   @@@ @@  @@ @@  @  @@ @@  @@
    // @@  @@     @@   @@ @@  @@ @@  @@     @@  @@@@ @@@@@@ @@  @  @@  @@@@
    // @@  @@     @@   @@ @@  @@ @@  @@     @@@@  @@ @@  @@ @@@ @ @@@   @@
    // @@  @@     @@@@@@  @@@@@@ @@  @@     @@@   @@ @@  @@     @       @@

    // Получить дополнительную информацию
    protected function getAddInfo()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result["images"] = $this->getImages();
        $result["files"] = $this->getFiles();

        $result["specifications"] = $this->getSpecifications();
        $result["similarProducts"] = $this->getSimilarProducts();
        $result["accompanyingProducts"] = $this->getAccompanyingProducts();
        $result["comments"] = $this->getComments();
        $result["reviews"] = $this->getReviews();
        $result["discounts"] = $this->getDiscounts();
        $result["crossGroups"] = $this->getCrossGroups();
        $result["modifications"] = $this->getModifications();
        $result["customFields"] = $this->getCustomFields();
        $result["countModifications"] = count($result["modifications"]);
        $result["options"] = $this->getOptions();
        $result["labels"] = $this->getLabels();
        if (empty($result["customFields"]))
            $result["customFields"] = false;

        return $result;
    }

    // Получить url
    private function getUrl($code, $id, $existCodes = [])
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $code_n = $code;
        $id = (int)$id;
        $u = new DB('shop_price', 'sp');
        $i = 1;
        while ($i < 1000) {
            $data = $u->findList("sp.code = '$code_n' AND id <> {$id}")->fetchOne();
            if ($data["id"] || in_array($code_n, $existCodes))
                $code_n = $code . "-$i";
            else return $code_n;
            $i++;
        }
        return uniqid();
    }


    // @@@@@@ @@@@@@ @@    @@ @@@@@@
    // @@     @@     @@    @@ @@
    // @@@@@@ @@@@@@  @@  @@  @@@@@@
    //     @@ @@       @@@@   @@
    // @@@@@@ @@@@@@    @@    @@@@@@

    // Сохранить
    public function save()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        # All Mode
        // исправить все
        $this->correctAll();

        // формирование артикля // при создании товара (если отличен от нуля и пуст)
        if (empty($this->input['article']) && count($this->input['ids']) < 2) { // isset($this->input['article']) &&
            if (empty($this->input['ids'])) {
                $u = new DB('shop_price');
                $u->select('MAX(id) AS mid');
                $res = $u->fetchOne();
                $res['mid'] += 1;
            } else {
                $res['mid'] = $this->input['ids'][0];
            }
            $this->input['article'] = sprintf("%03s", $this->input["idGroup"]) . '-' . sprintf("%03s", $res["mid"]);
        }


        if (isset($this->input['brand'], $this->input['ids'])) {
            $brand = (int)$this->input['brand']['id'];
            $idsStr = implode(",", $this->input['ids']);

            DB::exec("UPDATE `shop_price` SET `id_brand` = '" . $brand . "' WHERE `shop_price`.`id` IN (" . $idsStr . ");");

            return true;
        }

        DB::exec("ALTER TABLE `shop_price` CHANGE `code` `code` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");
        if (isset($this->input["code"]) && empty($this->input["code"]))
            $this->input["code"] = strtolower(se_translite_url($this->input["code"]));

        file_get_contents('http://' . HOSTNAME . "/lib/shoppreorder_checkCount.php?id={$this->input["id"]}");

        parent::save();

    }

    // сохранить все меры (объемы и веса)
    public function saveMeasure()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        try {
            $u = new DB('shop_price_measure');
            $u->select('id');
            $u->where('id_price=?', $this->input["id"]); // добавить ID если нет
            $res = $u->fetchOne();                              // получить одну


            $data = array();
            if ($res['id'])
                $data["id"] = $res['id'];
            $data["idPrice"] = $this->input["id"];
            $data["idWeightView"] = $this->input["idWeightView"];
            $data["idWeightEdit"] = $this->input["idWeightEdit"];
            $data["idVolumeView"] = $this->input["idVolumeView"];
            $data["idVolumeEdit"] = $this->input["idVolumeEdit"];

            $u = new DB('shop_price_measure');
            $u->setValuesFields($data);
            $u->save();
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить меры!";
            throw new Exception($this->error);
        }
    }

    // Правильные значения перед сохранением
    protected function correctValuesBeforeSave()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!$this->input["id"] && !$this->input["ids"] || isset($this->input["code"])) {
            if (empty($this->input["code"]))
                $this->input["code"] = strtolower(se_translite_url($this->input["name"]));
            $this->input["code"] = $this->getUrl($this->input["code"], $this->input["id"]);
        }
        if (isset($this->input["presence"]) && empty($this->input["presence"]))
            $this->input["presence"] = null;
    }

    // Сохранить изображения
    private function saveImages()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["images"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $images = $this->input["images"];
            if ($this->isNew) {
                foreach ($images as &$image)
                    unset($image["id"]);
                unset($image);
            }
            // обновление изображений
            $idsStore = "";

            foreach ($images as $image) {
                if ($image["id"] > 0) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $image["id"];
                    $u = new DB('shop_img', 'si');
                    $image["picture"] = $image["imageFile"];
                    $image["sort"] = $image["sortIndex"];
                    $image["pictureAlt"] = $image["imageAlt"];
                    $image["default"] = $image["isMain"];
                    $u->setValuesFields($image);
                    $u->save();
                }
            }
            $idsStr = implode(",", $idsProducts);
            if (!empty($idsStore)) {
                $u = new DB('shop_img', 'si');
                $u->where("id_price IN ($idsStr) AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB('shop_img', 'si');
                $u->where('id_price IN (?)', $idsStr)->deleteList();
            }

            $data = [];
            foreach ($images as $image)
                if (empty($image["id"]) || ($image["id"] <= 0)) {
                    foreach ($idsProducts as $idProduct) {
                        $data[] = array('id_price' => $idProduct, 'picture' => $image["imageFile"],
                            'sort' => (int)$image["sortIndex"], 'picture_alt' => $image["imageAlt"],
                            'default' => (int)$image["isMain"]);
                        $newImages[] = $image["imageFile"];
                    }
                }

            if (!empty($data))
                DB::insertList('shop_img', $data);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить изображения товара!";
            throw new Exception($this->error);
        }
    }

    private function saveOptions()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["options"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $options = $this->input["options"];
            $idsStr = implode(",", $idsProducts);
            $idsExists = [];
            foreach ($options as $option)
                foreach ($option['items'] as $items) {
                    if ($items["id"])
                        $idsExists[] = $items["id"];
                }
            $idsExists = implode(",", $idsExists);


            $u = new DB('shop_product_option');
            if (!$idsExists)
                $u->where('id_product IN (?)', $idsStr)->deleteList();
            else $u->where("NOT id IN ({$idsExists}) AND id_product IN (?)", $idsStr)->deleteList();
            foreach ($options as $option) {
                foreach ($option['items'] as $items) {
                    foreach ($idsProducts as $idProduct) {
                        $items["idProduct"] = $idProduct;
                        $items["price"] = $items["priceValue"];
                        $u = new DB('shop_product_option');
                        $u->setValuesFields($items);
                        //writeLog($items);
                        $u->save();
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить опции товара!";
            throw new Exception($this->error);
        }
    }

    // Сохранить файлы
    private function saveFiles()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["files"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $files = $this->input["files"];
            if ($this->isNew) {
                foreach ($files as &$file)
                    unset($file["id"]);
                unset($file);
            }
            // обновление изображений
            $idsStore = "";

            foreach ($files as $file) {
                if ($file["id"] > 0) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $file["id"];
                    $u = new DB('shop_files', 'si');
                    $file["file"] = $file["fileURL"];
                    $file["sort"] = $file["sortIndex"];
                    $file["name"] = $file["fileText"];
                    $u->setValuesFields($file);
                    $u->save();
                }
            }
            $idsStr = implode(",", $idsProducts);
            if (!empty($idsStore)) {
                $u = new DB('shop_files', 'si');
                $u->where("id_price IN ($idsStr) AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB('shop_files', 'si');
                $u->where('id_price IN (?)', $idsStr)->deleteList();
            }

            $data = array();
            foreach ($files as $file)
                if (empty($file["id"]) || ($file["id"] <= 0)) {
                    foreach ($idsProducts as $idProduct) {
                        $data[] = array(
                            'id_price' => $idProduct,
                            'file' => $file["fileURL"],
                            'sort' => (int)$file["sortIndex"],
                            'name' => $file["fileText"]
                        );
                    }
                }

            if (!empty($data))
                DB::insertList('shop_files', $data);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить файлы товара!";
            throw new Exception($this->error);
        }
    }

    // Получить группу спецификаций идентификаторов
    private function getIdSpecificationGroup($name)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (empty($name))
            return null;

        $u = new DB('shop_feature_group');
        $u->select('id');
        $u->where('name = "?"', $name);
        $result = $u->fetchOne();
        if (!empty($result["id"]))
            return $result["id"];

        $u = new DB('shop_feature_group');
        $u->setValuesFields(array("name" => $name));
        return $u->save();
    }

    // Получить идентификатор
    private function getIdFeature($idGroup, $name)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_feature', 'sf');
        $u->select('id');
        $u->where('name = "?"', $name);
        if ($idGroup)
            $u->andWhere('id_feature_group = ?', $idGroup);
        else $u->andWhere('id_feature_group IS NULL');
        $result = $u->fetchOne();
        if (!empty($result["id"]))
            return $result["id"];

        $u = new DB('shop_feature', 'sf');
        $data = [];
        if ($idGroup)
            $data["idFeatureGroup"] = $idGroup;
        $data["name"] = $name;
        return $u->save();
    }

    // Получить спецификацию по имени
    public function getSpecificationByName($specification)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $idGroup = $this->getIdSpecificationGroup($specification->nameGroup);
        $specification->idFeature = $this->getIdFeature($idGroup, $specification->name);
        return $specification;
    }

    public function getOptions($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = [];
        $id = $idProduct ? $idProduct : $this->input["id"];
        if (!$id)
            return $result;

        try {
            $u = new DB('shop_option', 'so');
            $u->select('so.*');
            $u->innerJoin('shop_option_value sov', 'sov.id_option = so.id');
            $u->innerJoin('shop_product_option spo', 'sov.id = spo.id_option_value');
            $u->where('spo.id_product = ?', $id);
            $u->orderBy('so.sort');
            $u->groupby('so.id');
            $result = $u->getList();
            foreach ($result as &$item) {
                $u = new DB('shop_product_option', 'spo');
                $u->select('spo.*, sov.name, spo.price as priceValue');
                $u->innerJoin('shop_option_value sov', 'sov.id = spo.id_option_value');
                $u->where('spo.id_product = ?', $id);
                $u->andwhere('sov.id_option = ?', $item['id']);
                $u->orderBy('spo.sort');
                $item['columns'] = array(array('id' => $item['id'], 'name' => $item['name']));
                $item['items'] = $u->getList();
            }
            return $result;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить опции товара!";
        }
    }

    // Получить пользовательские поля
    private function getCustomFields()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $idPrice = $this->input["id"];
        try {
            $u = new DB('shop_userfields', 'su');
            $u->select("cu.id, cu.id_price, cu.value, su.id id_userfield,
                      su.name, su.required, su.enabled, su.type, su.placeholder, su.description, su.values, sug.id id_group, sug.name name_group");
            $u->leftJoin('shop_price_userfields cu', "cu.id_userfield = su.id AND cu.id_price = {$idPrice}");
            $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
            $u->where('su.data = "product"');
            $u->groupBy('su.id');
            $u->orderBy('sug.sort');
            $u->addOrderBy('su.sort');
            $result = $u->getList();

            $groups = array();
            foreach ($result as $item) {
                $groups[intval($item["idGroup"])]["id"] = $item["idGroup"];
                $groups[intval($item["idGroup"])]["name"] = empty($item["nameGroup"]) ? "Без категории" : $item["nameGroup"];
                if ($item['type'] == "date")
                    $item['value'] = date('Y-m-d', strtotime($item['value']));
                $groups[intval($item["idGroup"])]["items"][] = $item;
            }
            $grlist = array();
            foreach ($groups as $id => $gr) {
                $grlist[] = $gr;
            }
            return $grlist;
        } catch (Exception $e) {
            return false;
        }
    }

    // Сохранить Технические характеристики
    private function saveSpecifications()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["specifications"]))
            return true;

        try {

            $idsProducts = $this->input["ids"];
            $isAddSpecifications = $this->input["isAddSpecifications"];
            $specifications = $this->input["specifications"];
            $idsStr = implode(",", $idsProducts);

            if (!$isAddSpecifications) {
                if (count($idsProducts) > 1) {
                    $delIdsArray = $this->getDiffFeatures($idsProducts);

                    $u = new DB('shop_modifications_feature', 'smf');
                    foreach ($delIdsArray as $die) {
                        $u->where("id_modification IS NULL AND id_price IN (?) AND id_feature = {$die['id_feature']} AND id_value = {$die['id_value']}", $idsStr)->deleteList();
                    }
                } else {
                    $u = new DB('shop_modifications_feature', 'smf');
                    $u->where("id_modification IS NULL AND id_price IN (?)", $idsStr)->deleteList();
                }
            }

            $m = new DB('shop_modifications_feature', 'smf');
            $m->select('id');
            foreach ($specifications as $specification) {
                foreach ($idsProducts as $idProduct) {
                    if ($isAddSpecifications) {
                        if (is_string($specification["valueString"]) && $specification["type"] == "string")
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND
							           value_string = '{$specification["value"]}'");

                        if (is_bool($specification["valueBool"]) && $specification["type"] == "bool")
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND
							           value_bool = '{$specification["value"]}'");

                        if (is_numeric($specification["valueNumber"]) && $specification["type"] == "number")
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND
							           value_number = '{$specification["valueNumber"]}'");

                        if (is_numeric($specification["idValue"]))
                            $m->where("id_price = {$idProduct} AND id_feature = {$specification["idFeature"]} AND
									   id_value = {$specification["idValue"]}");

                        $result = $m->fetchOne();
                        if ($result["id"])
                            continue;
                    }
                    /*
                    if ($specification["type"] == "number")
                        $specification["valueNumber"] = $specification["value"];
                    elseif ($specification["type"] == "string")
                        $specification["valueString"] = $specification["value"];
                    elseif ($specification["type"] == "bool")
                        $specification["valueBool"] = $specification["value"];
                    else
                         */
                    if (($specification["type"] == "colorlist" || $specification["type"] == "list") && empty($specification["idValue"]))
                        continue;
                    $data[] = array('id_price' => $idProduct, 'id_feature' => $specification["idFeature"],
                        'id_value' => $specification["idValue"],
                        'value_number' => $specification["valueNumber"],
                        'value_bool' => $specification["valueBool"], 'value_string' => $specification["valueString"]);
                }
            }
            if (!empty($data))
                DB::insertList('shop_modifications_feature', $data, true);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить спецификации товара!";
            throw new Exception($this->error);
        }
    }

    // Сохранить похожие продукты
    private function saveSimilarProducts()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["similarProducts"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $products = $this->input["similarProducts"];
            $idsExists = [];
            foreach ($products as $p)
                if ($p["id"])
                    $idsExists[] = $p["id"];
            $idsExists = array_diff($idsExists, $idsProducts);
            $idsExistsStr = implode(",", $idsExists);
            $idsStr = implode(",", $idsProducts);
            $u = new DB('shop_sameprice', 'ss');
            if ($idsExistsStr)
                $u->where("((NOT id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR
                           ((NOT id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr)->deleteList();
            else $u->where('id_price IN (?) OR id_acc IN (?)', $idsStr)->deleteList();
            $idsExists = [];
            if ($idsExistsStr) {
                $u->select("id_price, id_acc");
                $u->where("((id_acc IN ({$idsExistsStr})) AND id_price IN (?)) OR
                            ((id_price IN ({$idsExistsStr})) AND id_acc IN (?))", $idsStr);
                $objects = $u->getList();
                foreach ($objects as $item) {
                    $idsExists[] = $item["idAcc"];
                    $idsExists[] = $item["idPrice"];
                }
            };
            $data = [];
            foreach ($products as $p)
                if (empty($idsExists) || !in_array($p["id"], $idsExists))
                    foreach ($idsProducts as $idProduct)
                        $data[] = array('id_price' => $idProduct, 'id_acc' => $p["id"]);
            if (!empty($data))
                DB::insertList('shop_sameprice', $data);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить похожие товары!";
            throw new Exception($this->error);
        }
    }

    // Сохранить сопутствующие товары
    private function saveAccompanyingProducts()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["accompanyingProducts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id) {
                $idsAcc = [];
                foreach ($this->input["accompanyingProducts"] as $product) {

                    if ($product["id"]) {
                        $t = new DB("shop_accomp", "sa");
                        $t->select("sa.id");
                        $t->where("sa.id_acc = ?", $product["id"]);
                        $t->andWhere("sa.id_price = ?", $id);
                        $result = $t->fetchOne();
                        if (empty($result)) {
                            $t = new DB("shop_accomp", "sa");
                            $t->setValuesFields(["idPrice" => $id, "idAcc" => $product["id"]]);
                            $idsAcc[] = $t->save();
                        } else $idsAcc[] = $result["id"];
                    }

                    if ($product["idGroup"]) {
                        $t = new DB("shop_accomp", "sa");
                        $t->select("sa.id");
                        $t->where("sa.id_group = ?", $product["idGroup"]);
                        $t->andWhere("sa.id_price = ?", $id);
                        $result = $t->fetchOne();
                        if (empty($result)) {
                            $t = new DB("shop_accomp", "sa");
                            $t->setValuesFields(["idPrice" => $id, "idGroup" => $product["idGroup"]]);
                            $idsAcc[] = $t->save();
                        } else $idsAcc[] = $result["id"];
                    }

                }

                $t = new DB("shop_accomp", "sa");
                $t->where("id_price = ?", $id);
                if (count($idsAcc)) {
                    $idsAcc = implode(",", $idsAcc);
                    $t->andWhere("NOT id IN ($idsAcc)");
                }
                $t->deleteList();
            }

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить сопутствующие товары!";
            throw new Exception($this->error);
        }
    }

    // Сохранить коментарии
    private function saveComments()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["comments"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $comments = $this->input["comments"];
            $idsStr = implode(",", $idsProducts);
            $u = new DB('shop_comm', 'sc');
            $u->where('id_price IN (?)', $idsStr)->deleteList();
            foreach ($comments as $c) {
                $showing = 'N';
                $isActive = 'N';
                if ($c["isShowing"])
                    $showing = 'Y';
                if ($c["isActive"])
                    $isActive = 'Y';
                foreach ($idsProducts as $idProduct)
                    $data[] = array('id_price' => $idProduct, 'date' => $c["date"], 'name' => $c["name"],
                        'email' => $c["email"], 'commentary' => $c["commentary"], 'response' => $c["response"],
                        'showing' => $showing, 'is_active' => $isActive);
            }
            if (!empty($data))
                DB::insertList('shop_comm', $data);
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить комментарии товара!";
            throw new Exception($this->error);
        }
    }

    // Сохранить отзывы по товару
    private function saveReviews()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["reviews"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $reviews = $this->input["reviews"];
            $idsStr = implode(",", $idsProducts);
            $idsExists = [];
            foreach ($reviews as $review)
                if ($review["id"])
                    $idsExists[] = $review["id"];
            $idsExists = implode(",", $idsExists);
            $u = new DB('shop_reviews');
            if (!$idsExists)
                $u->where('id_price IN (?)', $idsStr)->deleteList();
            else $u->where("NOT id IN ({$idsExists}) AND id_price IN (?)", $idsStr)->deleteList();
            foreach ($reviews as $review) {
                foreach ($idsProducts as $idProduct) {
                    $review["idPrice"] = $idProduct;
                    $u = new DB('shop_reviews');
                    $u->setValuesFields($review);
                    $u->save();
                }
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить отзывы товара!";
            throw new Exception($this->error);
        }
    }

    /*
     * СОХРАНИТЬ ПЕРЕКРЕСТНЫЕ ГРУППЫ
     * crossGroups приходят нумерованным массивом.
     *      Группа - массив свойств, обязательные: id
     * delCrosGroups либо отсутствует (по умолчанию в методе Trye), либо равен "False"
     *
     * если crossGroups отсутствуют - завершение сохранения
     * проходим по id товаров и удаляем их Не главные группы (если не прописана отмена удаления в delCrosGroups)
     * сохраняем новые в shop_price_group
     * фильтруем повторяющиеся записи по id_price==id_price, id_group==id_group, is_main==is_main
     */
    private function saveCrossGroups()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["crossGroups"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $groups = $this->input["crossGroups"];

            $del = "True";
            if (!empty($this->input['delCrosGroups']))
                $del = $this->input['delCrosGroups'];
            $idsStr = implode(",", $idsProducts);
            if ($_SESSION['coreVersion'] > 520) {
                if ($del != "False") {
                    $u = new DB('shop_price_group', 'spg');
                    $u->where('NOT is_main AND id_price in (?)', $idsStr)->deleteList();
                    unset($u);
                };
                $chgr = array();
                foreach ($groups as $group) {
                    foreach ($idsProducts as $idProduct) {
                        if (empty($chgr[$idProduct][$group["id"]])) {
                            $data[] = array(
                                'id_price' => $idProduct,
                                'id_group' => $group["id"],
                                'is_main' => 0
                            );
                            $chgr[$idProduct][$group["id"]] = true;
                        }
                    }
                }
                if (!empty($data)) {
                    DB::insertList('shop_price_group', $data, 'INSERT IGNORE INTO');
                }

                DB::query("
                    DELETE a.* FROM shop_price_group a,
                        (SELECT
                            b.id_price, b.id_group, b.is_main, MIN(b.id) mid
                            FROM shop_price_group b
                            GROUP BY b.id_price, b.id_group, b.is_main
                        ) c
                    WHERE
                        a.id_price = c.id_price
                        AND a.id_group = c.id_group
                        AND a.is_main = c.is_main
                        AND a.id > c.mid
                ");
            } else
                foreach ($idsProducts as $id)
                    DB::saveManyToMany($id, $groups,
                        array(
                            "table" => "shop_group_price",
                            "key" => "price_id",
                            "link" => "group_id"
                        )
                    );
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить дополнительные категории товара!";
            throw new Exception($this->error);
        }
    }

    // @@@@@@ @@@@@@ @@  @@ @@@@@@   @@@@@@ @@  @@ @@    @@ @@@@@@  @@  @@ @@    @@
    // @@     @@  @@  @@@@  @@  @@   @@     @@ @@  @@   @@@ @@   @@ @@ @@  @@   @@@
    // @@     @@  @@   @@   @@@@@@   @@     @@@@   @@  @@@@ @@   @@ @@@@   @@  @@@@
    // @@     @@  @@  @@@@  @@       @@     @@ @@  @@@@  @@ @@   @@ @@ @@  @@@@  @@
    // @@@@@@ @@@@@@ @@  @@ @@       @@@@@@ @@  @@ @@@   @@ @@@@@@  @@  @@ @@@   @@

    // Сохранить скидки
    private function saveDiscounts()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        // если данные отсутствую, передаем просто Истину
        if (!isset($this->input["discounts"]))
            return true;

        // сохранения по id (к столбцу id_price) скидок в таблицу shop_discount_links
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["discounts"],
                    array("table" => "shop_discount_links", "key" => "id_price", "link" => "discount_id"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить скидки товара!";
            throw new Exception($this->error);
        }
    }

    // Разница массива
    private function diffArray($values, $stringMode = false)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $newValues = array();
        foreach ($values as $value) {
            array_push($newValues, array(
                'id' => $value['id'],
                'idFeature' => $value['idFeature']
            ));
        }
        sort($newValues);
        if ($stringMode) {
            $newValues = json_encode($newValues);
        }
        return $newValues;
    }

    /**
     *
     *
     */
    // Правильные изменения перед сохранением
    private function correctModificationsBeforeSave($tabs)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $newMod = array();
        foreach ($tabs as $tabIndex => $tab) {
            $newMod[$tabIndex] = $tab;
            $newMod[$tabIndex]['items'] = array();
            $searchBase = array();
            foreach ($tab['items'] as $itemIndex => $item) {
                if ($itemIndex == 0) {
                    $newMod[$tabIndex]['items'][] = $item;
                    $searchBase[] = $this->diffArray($item['values']);
                } else {
                    foreach ($searchBase as $example) {
                        if ($example == $this->diffArray($item['values'])) {
                            continue 2;
                        }
                    }
                    $newMod[$tabIndex]['items'][] = $item;
                    $searchBase[] = $this->diffArray($item['values']);
                }
            }
        }
        return $newMod;
    }

    // Сохранить модификации товара
    private function saveModifications()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["modifications"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $ifAdd = !empty($this->input["add"]);

            $modifications = $this->correctModificationsBeforeSave($this->input["modifications"]);

            if ($this->isNew)
                foreach ($modifications as &$mod)
                    foreach ($mod["items"] as &$item) {
                        $item["id"] = null;
                        if (empty($item["article"]))
                            $item["article"] = $this->input["article"];
                    }

            $idsStr = implode(",", $idsProducts);
            $isMultiMode = sizeof($idsProducts) > 1;

            $namesToIds = [];
            if (!empty($this->newImages)) {
                $imagesStr = '';
                foreach ($this->newImages as $image) {
                    if (!empty($imagesStr))
                        $imagesStr .= ',';
                    $imagesStr .= "'$image'";
                }
                $u = new DB('shop_img', 'si');
                $u->select('id, picture');
                $u->where('picture IN (?)', $imagesStr);
                $u->andWhere('id_price IN (?)', $idsStr);
                $objects = $u->getList();

                foreach ($objects as $item)
                    $namesToIds[$item['picture']] = $item['id'];
            }

            // Собираем существующие модификации
            if (!$isMultiMode) {
                $idsUpdateM = null;
                foreach ($modifications as $mod) {
                    foreach ($mod["items"] as $item) {
                        if (!empty($item["id"])) {
                            if (!empty($idsUpdateM))
                                $idsUpdateM .= ',';
                            $idsUpdateM .= $item["id"];
                        }
                    }
                }
            }
            // Удаление лишних модификаций когда идет замена
            #if (!$ifAdd) {
            $u = new DB('shop_modifications', 'sm');
            if (!empty($idsUpdateM))
                $u->where("NOT id IN ($idsUpdateM) AND id_price in (?)", $idsStr)->deleteList();
            else $u->where("id_price IN (?)", $idsStr)->deleteList();
            /*} else {
                $u = new DB('shop_modifications', 'sm');
                $u->select('sm.id, sm.id_price, smf.id_feature, smf.id_value');
                $u->innerJoin('shop_modifications_feature smf', 'smf.id_modification = sm.id');
                $u->where('sm.id_price IN (?)', $idsStr);
                $tems = $u->getList();
            }*/
            // новые модификации
            $dataM = [];
            $dataF = [];
            $dataI = [];
            $result = DB::query("SELECT MAX(id) FROM shop_modifications")->fetch();
            $i = $result[0] + 1;

            foreach ($modifications as $mod) {
                foreach ($mod["items"] as $item) {
                    if (empty($item["id"]) || $isMultiMode) {
                        $count = null;
                        if ($item["count"] >= 0)
                            $count = $item["count"];
                        foreach ($idsProducts as $idProduct) {
                            $notAdd = false;
                            $newDataM = $newDataF = $newDataI = null;

                            $newDataM = array(
                                'id' => $i,
                                'code' => $item["article"],
                                'id_mod_group' => $mod["id"],
                                'id_price' => $idProduct,
                                'value' => $item["priceRetail"],
                                'value_opt' => $item["priceSmallOpt"],
                                'value_opt_corp' => $item["priceOpt"],
                                'count' => $count,
                                'sort' => (int)$item["sortIndex"],
                                'description' => $item["description"]);

                            foreach ($item["values"] as $v)
                                $dataF[] = array(
                                    'id_price' => $idProduct,
                                    'id_modification' => $i,
                                    'id_feature' => $v["idFeature"],
                                    'id_value' => $v["id"]);
                            foreach ($item["images"] as $img) {
                                if ($img["id"] <= 0)
                                    $img["id"] = $namesToIds[$img["imageFile"]];
                                $newDataI = array(
                                    'id_modification' => $i,
                                    'id_img' => $img["id"],
                                    'sort' => $img["sortIndex"]);
                            }

                            if (isset($tems) || $ifAdd) {
                                foreach ($tems as $it) {
                                    if ($it['idPrice'] == $newDataM['id_price']/* and $it['idValue'] == $newDataF['id_value']*/) {
                                        $notAdd = true;
                                    }
                                }
                            }

                            if (!$notAdd) {
                                if (!empty($newDataM))
                                    $dataM[] = $newDataM;
                                if (!empty($newDataF))
                                    $dataF[] = $newDataF;
                                if (!empty($newDataI))
                                    $dataI[] = $newDataI;
                                $i++;
                            }
                        }
                    }
                }
            }
            try {


                if (!empty($dataM)) {
                    DB::insertList('shop_modifications', $dataM);
                    if (!empty($dataF)) {
                        DB::insertList('shop_modifications_feature', $dataF);
                    }
                    if (!empty($dataI)) {
                        DB::insertList('shop_modifications_img', $dataI);
                    }
                    $dataI = null;
                }

            } catch (Exception $e) {
                throw new Exception();
            }
            // обновление модификаций
            if (!$isMultiMode) {
                foreach ($modifications as $mod) {
                    foreach ($mod["items"] as $item) {
                        if (!empty($item["id"])) {
                            $u = new DB('shop_modifications', 'sm');
                            $item["code"] = $item["article"];
                            $item["value"] = $item["priceRetail"];
                            $item["valueOpt"] = $item["priceSmallOpt"];
                            $item["valueOptCorp"] = $item["priceOpt"];
                            $item["sort"] = $item["sortIndex"];
                            $u->setValuesFields($item);
                            $u->save();

                            $u = new DB('shop_modifications_img', 'smi');
                            $u->where("id_modification = ?", $item["id"])->deleteList();
                            $dataI = [];
                            foreach ($item["images"] as $img) {
                                if ($img["id"] <= 0)
                                    $img["id"] = $namesToIds[$img["imageFile"]];
                                $dataI[] = array('id_modification' => $item["id"], 'id_img' => $img["id"],
                                    'sort' => $img["sortIndex"]);
                            }
                            if (!empty($dataI))
                                DB::insertList('shop_modifications_img', $dataI);
                        }
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить модификации товара!";
            throw new Exception($this->error);
        }
    }

    // Сохранить категорию товара
    private function saveIdGroup()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (($_SESSION['coreVersion'] < 530) || !isset($this->input["idGroup"]))
            return true;

        try {
            $idsProducts = $this->input["ids"];
            $idGroup = $this->input["idGroup"];
            $idsStr = implode(",", $idsProducts);
            $u = new DB('shop_price_group');
            $u->where('is_main AND id_price IN (?)', $idsStr)->deleteList();

            $chgr = array();
            foreach ($idsProducts as $idProduct) {

                if (empty($chgr[$idProduct][$idGroup])) {
                    $u = new DB('shop_price_group');
                    $u->where('id_price = ? AND id_group = ' . $idGroup, $idProduct)->deleteList();

                    $group = array();
                    $group["idPrice"] = $idProduct;
                    $group["idGroup"] = $idGroup;
                    $group["isMain"] = true;

                    $u = new DB('shop_price_group');
                    $u->setValuesFields($group);
                    $u->save();

                    $chgr[$idProduct][$idGroup] = true;
                }
            }

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить категорию товара!";
            throw new Exception($this->error);
        }
    }

    // Сохранить доп. информацию о товаре
    private function saveCustomFields()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["customFields"]) && !$this->input["customFields"])
            return true;

        try {
            $idProduct = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = [];
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $field["idPrice"] = $idProduct;
                $u = new DB('shop_price_userfields', 'cu');
                $u->setValuesFields($field);
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о товаре!";
            throw new Exception($this->error);
        }
    }

    // Сохранить добавленную инфу
    protected function saveAddInfo()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["ids"]))
            return false;

        return $this->saveImages() && $this->saveSpecifications() && $this->saveSimilarProducts() &&
            $this->saveAccompanyingProducts() && $this->saveComments() && $this->saveReviews() &&
            $this->saveCrossGroups() && $this->saveDiscounts() && $this->saveMeasure() &&
            $this->saveModifications() && $this->saveIdGroup() &&
            $this->saveCustomFields() && $this->saveFiles() && $this->saveOptions() && $this->saveLabels();
    }


    // @@@@@@ @@@@@@ @@@@@@@@ @@@@@@ @@@@@@ @@@@@@ @@  @@ @@@@@@
    // @@     @@        @@    @@     @@  @@ @@  @@ @@  @@ @@  @@
    // @@     @@@@@@    @@    @@     @@@@@@ @@  @@ @@  @@ @@@@@@
    // @@  @@ @@        @@    @@  @@ @@ @@  @@  @@ @@  @@ @@
    // @@@@@@ @@@@@@    @@    @@@@@@ @@  @@ @@@@@@ @@@@@@ @@

    // Получить группЫ
    protected function getGroup($groups, $idGroup)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!$idGroup)
            return null;

        // разложение строки на элементы
        $groupsLine = explode(",", $idGroup);

        // прогонка всех элементов через цикл
        $nameGroups = null;
        foreach ($groupsLine as $groupLine) {
            foreach ($groups as $group) {
                if ($group["id"] == $groupLine) {
                    if ($group['upid'])
                        $nameGroups .= $this->getGroup($groups, $group['upid']) . "/" . $group["name"] . ',';
                    else
                        $nameGroups .= $group["name"] . ',';
                }
            }
        }
        $nameGroups = chop($nameGroups, ','); // удаление поседней запятой

        return $nameGroups;
    }


    // @@@@@@ @@@@@@ @@@@@@@@ @@@@@@ @@@@@@ @@@@@@ @@  @@ @@@@@@ @@@@@@ @@@@@
    // @@     @@        @@    @@     @@  @@ @@  @@ @@  @@ @@  @@ @@         @@
    // @@     @@@@@@    @@    @@     @@@@@@ @@  @@ @@  @@ @@@@@@ @@@@@  @@@@@
    // @@  @@ @@        @@    @@  @@ @@ @@  @@  @@ @@  @@ @@         @@     @@
    // @@@@@@ @@@@@@    @@    @@@@@@ @@  @@ @@@@@@ @@@@@@ @@     @@@@@  @@@@@

    // Получить группЫ 53
    protected function getGroup53($groups, $idGroup)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!$idGroup)
            return null;

        // разложение строки на элементы
        $groupsLine = explode(",", $idGroup);

        // выстраивание пути из ид в имена
        $nameGroups = null;
        foreach ($groupsLine as $groupLine) {
            foreach ($groups as $group) {
                if ($group["id"] == $groupLine) {
                    $nameGroups .= $group["name"] . ',';
                }
            }
        }
        $nameGroups = chop($nameGroups, ','); // удаление последней запятой

        return $nameGroups;
    }


    // @@@@@@ @@  @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
    // @@      @@@@  @@  @@ @@  @@ @@  @@    @@
    // @@@@@@   @@   @@@@@@ @@  @@ @@@@@@    @@
    // @@      @@@@  @@     @@  @@ @@ @@     @@
    // @@@@@@ @@  @@ @@     @@@@@@ @@  @@    @@

    // Экспорт
    public function export()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        // прием данных из формы
        $input = $this->input;

        // определяем параметры файла
        $fileName = "export_products.xlsx";
        $oldFileName = "old_export_products.xlsx";
        $filePath = DOCUMENT_ROOT . "/files/tempfiles";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath);
        $temporaryFilePath = "{$filePath}";
        // создать директорию, если отсутствует
        // if (!is_dir($temporaryFilePath))
        //     mkdir($temporaryFilePath, 0777);
        $oldFilePath = $filePath . "/{$oldFileName}";
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/tempfiles/{$fileName}";

        try {

            $export = new ProductExport($this->input);

            /*
             * ЭКСПОРТ прохобит в 2 этапа:
             *   1. ПОЛУЧЕНИЕ ПРЕВЬЮ: Получение заголовков + модификаций для чекбокс-листа (выбора экспортируемых столбцов)
             *   2. ПОЛУЧЕНИЕ ФАЙЛА:  Получение заголовков + модификаций + листа записей по товарам с записью в файл
             *
             * тк в обоих шагах используется function export() - создана вилка.
             * На переключение влияет параметр  $this->input['statusPreview']  true или отсутствие
             */

            if ($input['statusPreview'] == true) {
                // получаем заголовки + модификации товаров
                $headerCSV = $export->previewExport($temporaryFilePath);
            } else {
                // получение данных из базы (заголовки,товары)
                $pages = $export->mainExport($input, $fileName, $filePath, $oldFilePath, $temporaryFilePath);
            };

            // передача в Ajax
            $this->result['pages'] = $pages;
            $this->result['url'] = $urlFile;
            $this->result['name'] = $fileName;
            $this->result['headerCSV'] = $headerCSV;

        } catch (Exception $e) {
            // ошибка экспорта
            $this->error = $e->getMessage();
            //$this->error = "Не удаётся экспортировать товары!";
            throw new Exception($this->error);
        }
    }


    // @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
    // @@  @@ @@  @@ @@        @@
    // @@@@@@ @@  @@ @@@@@@    @@
    // @@     @@  @@     @@    @@
    // @@     @@@@@@ @@@@@@    @@

    // После
    public function post($tempFile = FALSE)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $this->rmdir_recursive(DOCUMENT_ROOT . "/files/tempfiles");  // очистка директории с временными файлами
        unset($_SESSION["getId"]);                                       // очистка временных данных по связям товар-группа
        if ($items = parent::post(true)) {
            $this->import($items[0]["url"], $items[0]["name"]);
        }
    }


    // @@@@@@ @@     @@ @@@@@@ @@@@@@ @@@@@@ @@@@@@@@
    //   @@   @@@   @@@ @@  @@ @@  @@ @@  @@    @@
    //   @@   @@ @@@ @@ @@@@@@ @@  @@ @@@@@@    @@
    //   @@   @@  @  @@ @@     @@  @@ @@ @@     @@
    // @@@@@@ @@     @@ @@     @@@@@@ @@  @@    @@

    // Импорт
    public function import($url = null, $fileName = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        if (!empty($_POST)) $_SESSION["options"] = $_POST;
        /*
         * if   превью импорта
         * else непосредственный импорт
         *
         * $this->result - отправка в Ajax
         * $headerCSV - заголовки CSV?
         */
        if (!is_null($fileName)) {
            return $this->productsImport($url, $fileName);
        } else {
            $import = new Import($this->input);
            $options = $_SESSION["options"];
            $pages = $import->startImport(
                $this->input['filename'],
                false,
                $options,
                $this->input['prepare'][0],
                $this->input['cycleNum']
            );

            /**
             * @var float $this->result['pages']        всего страниц
             * @var Integer $this->result['countPages'] колво прочитанных страниц
             * @var Integer $this->result['cycleNum']   колво обработанных страниц
             */
            $this->result['pages'] = $_SESSION["pages"];
            $this->result['countPages'] = $_SESSION["countPages"];
            $this->result['cycleNum'] = $_SESSION["cycleNum"];
            return true;
        }
    }


    // @@@@@@ @@@@@@ @@@@@@ @@@@@@    @@@@@@ @@     @@ @@@@@@
    // @@  @@ @@  @@ @@  @@ @@   @@     @@   @@@   @@@ @@  @@
    // @@@@@@ @@@@@@ @@  @@ @@   @@     @@   @@ @@@ @@ @@@@@@
    // @@     @@ @@  @@  @@ @@   @@     @@   @@  @  @@ @@
    // @@     @@  @@ @@@@@@ @@@@@@    @@@@@@ @@     @@ @@

    // Превью импорта
    private function productsImport($url, $fileName)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $import = new Import($this->input);
        $options = $_SESSION["options"];
        return $this->result = $import->startImport(
            $fileName,
            true,
            $options,
            $this->input['prepare'][0],
            0
        );
    }

    /*
       private function importFromYml($fileUrl)
       {
           $url = "http://" . HOSTNAME . "/lib/loader_from_yml.php";
           $ch = curl_init($url);
           $data["serial"] = DB::$dbSerial;
           $data["db_password"] = DB::$dbPassword;
           $data["url_yml"] = $fileUrl;
           curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
           curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
           curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
           $result = curl_exec($ch);
           if ($result != "ok")
               $this->error = "Не удаётся импортировать товары с заданными параметрами!";
       }

       private function importFromCsv($filePath)
       {
           $isInsertMode = !empty($_POST["type"]) ? $_POST["type"] : false;
           $rusCols = $this->rusCols;
           $trCols = array_flip($rusCols);
           $rows = $this->getArrayFromCsv($filePath);

           // START: RUS to ENG keys
           $newsRows = [];
           foreach ($rows as $row) {
               $newRow = [];
               foreach ($row as $key => $value) {
                   if (key_exists($key, $trCols)) {
                       $newRow[$trCols[$key]] = $value;
                   } else $newRow[$key] = $value;
               }
               if ($newRow)
                   $newsRows[] = $newRow;
           }
           $rows = $newsRows;
           unset($newsRows);
           // END: RUS to ENG keys


           // START: Поиск моддификаций
           $isModificationMode = false; // режим с модификациями
           $featuresCols = [];
           $featuresKeys = [];
           $modsGroupsKeys = [];
           if ($rows) {
               $cols = array_keys($rows[0]);
               foreach ($cols as $col)
                   if (!in_array($col, $trCols)) {
                       $featuresCols[] = $col;
                       $name = explode('#', $col);
                       if (count($name) == 2) {
                           if (!in_array($name[0], $modsGroupsKeys))
                               $modsGroupsKeys[$name[0]] = null;
                           if (!in_array($name[1], $featuresKeys))
                               $featuresKeys[$name[1]] = null;
                           $isModificationMode = true;
                       }
                   }
           }

           $lastVal = null;
           $lastRow = null;
           $goodsInsert = [];
           $goodsUpdate = [];
           $groupsKeys = [];
           $featureValuesKeys = [];
           $groupTypesMods = [];
           $i = 0;
           foreach ($rows as &$row) {
               $mods = [];
               $mods['article'] = $row['article'];
               $mods['price'] = $row['price'];
               $mods['count'] = $row['count'];
               $mods['images'] = $row['images'];
               $mods['type'] = 0;
               if ($isModificationMode)
                   foreach ($featuresCols as $col) {
                       $cols = explode('#', $col);
                       if (count($cols) == 2) {
                           $mods['groupModifications'] = $cols[0];
                           $groupTypesMods[$cols[0]][$cols[1]] = null;
                       }
                       if (count($cols) == 2 && !empty($row[$col])) {
                           $mods["features"][$cols[1]] = $row[$col];
                           $featureValuesKeys[$cols[1]][$row[$col]] = null;
                       }
                   }
               if ((!empty($row['id']) && $row['id'] != $lastVal) ||
                   (empty($row['id']) && !empty($row['name']) && $row['name'] != $lastVal)
               ) {
                   foreach ($featuresCols as $col)
                       unset($row[$col]);
                   if (!$isInsertMode)
                       $goodsUpdate[] = &$row;
                   else $goodsInsert[] = &$row;
                   $lastRow = &$row;
                   $lastVal = !empty($row['id']) ? $row['id'] : $row['name'];
                   if (!empty($row['category']))
                       $groupsKeys[str_replace("/ ", "/", $row['category'])] = null;
                   if (!empty($row['features'])) {
                       $features = explode(';', $row['features']);
                       foreach ($features as $feature) {
                           $f = explode('#', $feature);
                           if (count($f) == 2) {
                               $featureName = $f[0];
                               $featureValue = $f[1];
                               if (!in_array($featureName, $featuresKeys))
                                   $featuresKeys[$featureName] = null;
                               if (!empty($featureValue))
                                   $featureValuesKeys[$featureName][$featureValue] = null;
                           }
                       }
                   }
               }
               if ($isModificationMode)
                   $lastRow['modifications'][] = $mods;
           }
           try {
               DB::beginTransaction();
               // добавление товаров
               if ($goodsInsert) {
                   // добавление группы товаров
                   $u = new DB('shop_group', 'sg');
                   if (CORE_VERSION != "5.2") {
                       $u->select('sg.id, GROUP_CONCAT(sgp.name ORDER BY sgt.level SEPARATOR "/") name');
                       $u->innerJoin("shop_group_tree sgt", "sg.id = sgt.id_child");
                       $u->innerJoin("shop_group sgp", "sgp.id = sgt.id_parent");
                       $u->orderBy('sgt.level');
                   } else {
                       $u->select('sg.*');
                       $u->orderBy('sg.id');
                   }
                   $u->groupBy('sg.id');
                   $groups = $u->getList();
                   foreach ($groups as $group) {
                       if (CORE_VERSION != "5.2")
                           $path = $this->getGroup53($groups, $group['id']);
                       else $path = $this->getGroup($groups, $group['id']);
                       if ($path)
                           $groupsKeys[$path] = $group['id'];
                   }

                   foreach ($groupsKeys as $key => $value) {
                       if (!$value) {
                           $names = explode("/", $key);
                           $idParent = null;
                           foreach ($names as $name) {
                               if (CORE_VERSION != "5.2")
                                   $idParent = $this->createGroup53($groups, $idParent, $name);
                               else $idParent = $this->createGroup($groups, $idParent, $name);
                           }
                           $groupsKeys[$key] = $idParent;
                       }
                   }
                   // добавление группы модификации
                   $newModsGroupsKeys = [];
                   if ($isModificationMode && $modsGroupsKeys) {
                       $u = new DB('shop_modifications_group', 'smg');
                       $u->select('id, name');
                       $u->orderBy('id');
                       $modsGroups = $u->getList();
                       $id = 0;
                       foreach ($modsGroups as $modGroup) {
                           $modsGroupsKeys[$modGroup['name']] = $modGroup['id'];
                           $id = $id < $modGroup['id'] ? $modGroup['id'] + 1 : $id;
                       }
                       foreach ($modsGroupsKeys as $key => $value) {
                           if (empty($value))
                               $dataModsGroups[] = array('id' => $value = ++$id, 'name' => $key);
                           $newModsGroupsKeys[$key] = $value;
                       }
                       if (!empty($dataModsGroups))
                           DB::insertList('shop_modifications_group', $dataModsGroups);
                       unset($modsGroupsKeys);
                       unset($dataModsGroups);
                   }
                   // добавление параметров для модификаций
                   $newFeaturesKeys = [];
                   if ($featuresKeys) {
                       $u = new DB('shop_feature', 'sf');
                       $u->select('id, name, type');
                       $u->orderBy('id');
                       $features = $u->getList();
                       $id = 0;
                       foreach ($features as $feature) {
                           $featuresKeys[$feature['name']] = $feature['id'];
                           $id = $id < $feature['id'] ? $feature['id'] + 1 : $id;
                       }
                       foreach ($featuresKeys as $key => $value) {
                           if (empty($value))
                               $dataFeatures[] = array('id' => $value = ++$id, 'name' => $key, 'type' => 'list');
                           $newFeaturesKeys[$key] = $value;
                       }

                       if (!empty($dataFeatures))
                           DB::insertList('shop_feature', $dataFeatures);
                       unset($featuresKeys);
                       unset($dataFeatures);
                   }
                   // добавление значений для параметров
                   $newValuesKeys = [];
                   if ($featureValuesKeys) {
                       $u = new DB('shop_feature_value_list', 'sfvl');
                       $u->select('sfvl.id, sfvl.value, sf.name feature');
                       $u->innerJoin('shop_feature sf', 'sf.id = sfvl.id_feature');
                       $u->orderBy('id');
                       $values = $u->getList();
                       $id = 0;
                       foreach ($values as $value) {
                           $featureValuesKeys[$value['feature']][$value['value']] = $value['id'];
                           $id = $id < $value['id'] ? $value['id'] + 1 : $id;
                       }
                       foreach ($featureValuesKeys as $key => $val) {
                           $idFeature = array_key_exists($key, $newFeaturesKeys) ? $newFeaturesKeys[$key] : null;
                           foreach ($val as $k => $v) {
                               if (!empty($idFeature) && empty($v))
                                   $dataFeaturesValues[] = array('id' => $v = ++$id, 'id_feature' => $idFeature, 'value' => $k);
                               $newValuesKeys[$key][$k] = $v;
                           }
                       }
                       if (!empty($dataFeaturesValues))
                           DB::insertList('shop_feature_value_list', $dataFeaturesValues);
                       unset($dataFeaturesValues);
                       unset($featureValuesKeys);
                   }
                   // объединение модификаций в группу (shop_group_feature)
                   if ($isModificationMode && $groupTypesMods) {
                       $u = new DB('shop_group_feature', 'sgf');
                       $u->select('sgf.id, sf.name feature, smg.name `group`');
                       $u->innerJoin('shop_feature sf', 'sf.id = sgf.id_feature');
                       $u->innerJoin('shop_modifications_group smg', 'smg.id = sgf.id_group');
                       $u->orderBy('id');
                       $rows = $u->getList();
                       foreach ($rows as $row)
                           $groupTypesMods[$row['group']][$row['feature']] = $row['id'];
                       foreach ($groupTypesMods as $key => $value) {
                           $idGroup = array_key_exists($key, $newModsGroupsKeys) ? $newModsGroupsKeys[$key] : null;
                           foreach ($value as $k => $v) {
                               $idFeature = array_key_exists($k, $newFeaturesKeys) ? $newFeaturesKeys[$k] : null;
                               if (!empty($idGroup) && !empty($idFeature) && empty($v))
                                   $dataTypesMods[] = array('id_feature' => $idFeature, 'id_group' => $idGroup);
                           }
                       }
                       if (!empty($dataTypesMods))
                           DB::insertList('shop_group_feature', $dataTypesMods);
                   }
                   // добавление товаров
                   $u = new DB('shop_price', 'sp');
                   $u->select('MAX(id) maxId');
                   $result = $u->fetchOne();
                   $idProduct = $result["maxId"];
                   $u = new DB('shop_modifications', 'sm');
                   $u->select('MAX(id) maxId');
                   $result = $u->fetchOne();
                   $idModification = $result["maxId"];
                   $dataGoodsGroups = [];
                   $rowInsert = 0;
                   $rowCount = 0;
                   $countGoods = count($goodsInsert);
                   $codes = [];
                   foreach ($goodsInsert as &$goodsItem) {
                       $idProduct++;
                       $images = !empty($goodsItem['images']) ? explode(";", $goodsItem['images']) : [];
                       $goodsItem['idGroup'] = $IdGroup = !empty($goodsItem['category']) ?
                           $groupsKeys[str_replace("/ ", "/", $goodsItem['category'])] : null;
                       if (empty($goodsItem['code']))
                           $goodsItem['code'] = strtolower(se_translite_url($goodsItem['name']));
                       $goodsItem['code'] = $this->getUrl($goodsItem['code'], 'shop_price', $codes);
                       $codes[] = $goodsItem['code'];
                       $price = $goodsItem['price'];
                       if (($ind = strpos($price, '+')) || ($ind = strpos($price, '*')))
                           $price = substr($price, 0, $ind - 1);
                       $count = $goodsItem['count'];
                       if ($isModificationMode) {
                           $count = empty($goodsItem['modifications']) ? $goodsItem['count'] : null;
                           if (!empty($goodsItem['modifications'])) {
                               foreach ($goodsItem['modifications'] as $mod) {
                                   if ($mod['count'] > 0)
                                       $count += $mod['count'];
                                   $codeM = empty($mod['article']) ? $goodsItem['article'] : $mod['article'];
                                   $valueM = !empty($mod['price']) ? $mod['price'] : 'null';
                                   if (($ind = strpos($valueM, '+')) || ($ind = strpos($valueM, '*')))
                                       $valueM = substr($valueM, $ind + 1, strlen($valueM) - $ind);
                                   $countM = !empty($mod['count']) || ($mod['count'] == '0.000') ? $mod['count'] : 'null';
                                   $idModGroup = !empty($mod['groupModifications']) ? $newModsGroupsKeys[$mod['groupModifications']] : null;
                                   if ($idModGroup) {
                                       $dataModifications[] = array("id" => ++$idModification, "id_mod_group" => $idModGroup,
                                           "id_price" => $idProduct, 'code' => $codeM,
                                           'value' => $valueM, 'count' => $countM);
                                       if (!empty($mod['features'])) {
                                           $featuresM = $mod['features'];
                                           foreach ($featuresM as $key => $val) {
                                               $idFeature = array_key_exists($key, $newFeaturesKeys) ? $newFeaturesKeys[$key] : null;
                                               if (!$idFeature)
                                                   continue;
                                               $idValue = $newValuesKeys[$key][$val];
                                               if (!$idValue)
                                                   continue;
                                               $dataModFeatures[] = array("id_price" => $idProduct, 'id_modification' => $idModification,
                                                   'id_feature' => $idFeature, 'id_value' => $idValue);
                                           }
                                       }
                                   }
                                   $images = array_merge($images, !empty($mod['images']) ? explode(";", $mod['images']) : []);
                               }
                           }
                       }
                       if (!empty($goodsItem['features'])) {
                           $features = explode(';', $goodsItem['features']);
                           foreach ($features as $feature) {
                               $f = explode('#', $feature);
                               if (count($f) == 2) {
                                   $featureName = $f[0];
                                   $featureValue = $f[1];
                                   $idFeature = array_key_exists($featureName, $newFeaturesKeys) ? $newFeaturesKeys[$featureName] : null;
                                   if (!$idFeature)
                                       continue;
                                   $idValue = $newValuesKeys[$featureName][$featureValue];
                                   if (!$idValue)
                                       continue;
                                   $dataModFeatures[] = array("id_price" => $idProduct, 'id_feature' => $idFeature, 'id_value' => $idValue);
                               }
                           }
                       }
                       $images = array_unique($images);
                       if (empty($count) && $count != "0.000")
                           $count = -1;
                       $measure = !empty($goodsItem['measurement']) ? $goodsItem['measurement'] : 'null';
                       $weight = !empty($goodsItem['weight']) ? $goodsItem['weight'] : 'null';
                       $volume = !empty($goodsItem['volume']) ? $goodsItem['volume'] : 'null';
                       $description = !empty($goodsItem['description']) ? $goodsItem['description'] : 'null';
                       $fullDescription = !empty($goodsItem['fullDescription']) ? $goodsItem['fullDescription'] : 'null';
                       $codeCurrency = !empty($goodsItem['codeCurrency']) ? $goodsItem['codeCurrency'] : 'RUB';
                       $metaHeader = !empty($goodsItem['metaHeader']) ? $goodsItem['metaHeader'] : 'null';
                       $metaKeywords = !empty($goodsItem['metaKeywords']) ? $goodsItem['metaKeywords'] : 'null';
                       $metaDescription = !empty($goodsItem['metaDescription']) ? $goodsItem['metaDescription'] : 'null';
                       if (CORE_VERSION != "5.2" && $goodsItem['idGroup'])
                           $dataGoodsGroups[] = array("id_group" => $goodsItem['idGroup'], "id_price" => $idProduct, "is_main" => 1);
                       $dataGoods[] = array("id" => $idProduct, "code" => $goodsItem['code'], "article" => $goodsItem['article'],
                           "id_group" => $IdGroup, "name" => $goodsItem['name'], 'price' => $price, 'presence_count' => $count,
                           'text' => $fullDescription, 'note' => $description, 'measure' => $measure, 'weight' => $weight,
                           'volume' => $volume, 'curr' => $codeCurrency, "title" => $metaHeader, "keywords" => $metaKeywords,
                           "description" => $metaDescription);
                       $i = 0;
                       foreach ($images as $image) {
                           $dataImages[] = array("id_price" => $idProduct, "picture" => $image, "default" => !$i);
                           $i++;
                       }
                       ++$rowCount;
                       if (++$rowInsert == 500 || ($rowCount >= $countGoods)) {
                           if (!empty($dataGoods)) {
                               DB::insertList('shop_price', $dataGoods);
                               $dataGoods = null;
                           }
                           if (!empty($dataImages)) {
                               DB::insertList('shop_img', $dataImages);
                               $dataImages = null;
                           }
                           if (!empty($dataModifications)) {
                               DB::insertList('shop_modifications', $dataModifications);
                               $dataModifications = null;
                           }
                           if (!empty($dataModFeatures)) {
                               DB::insertList('shop_modifications_feature', $dataModFeatures);
                               $dataModFeatures = null;
                           }
                           if (!empty($dataGoodsGroups)) {
                               DB::insertList('shop_price_group', $dataGoodsGroups);
                               $dataGoodsGroups = null;
                           }
                           $rowInsert = 0;
                       }
                   }
               }


   //            // обновление товаров
   //            //        if ($goodsUpdate) {
   //            //            $sql = null;
   //            //            foreach ($goodsUpdate as $goodsItem) {
   //            //                $sqlItem = 'UPDATE shop_price SET ';
   //            //                $fields = [];
   //            //                if (!empty($goodsItem['Code']))
   //            //                    $fields[] = "code = '{$goodsItem['Code']}'";
   //            //                if (!empty($goodsItem['Article']))
   //            //                    $fields[] = "article = '{$goodsItem['Article']}'";
   //            //                if (!empty($goodsItem['Name']))
   //            //                    $fields[] = "name = '{$goodsItem['Name']}'";
   //            //                if (!empty($goodsItem['Price'])) {
   //            //                    $price = $goodsItem['Price'];
   //            //                    if (($ind = strpos($price, '+')) || ($ind = strpos($price, '*')))
   //            //                        $price = substr($price, 0, $ind - 1);
   //            //                    $fields[] = "price = '{$price}'";
   //            //                }
   //            //                if (!empty($goodsItem['CodeCurrency']))
   //            //                    $fields[] = "curr = '{$goodsItem['CodeCurrency']}'";
   //            //                if (!empty($goodsItem['Count']))
   //            //                    $fields[] = "presence_count = '{$goodsItem['Count']}'";
   //            //                if (!empty($goodsItem['Measurement']))
   //            //                    $fields[] = "measure = '{$goodsItem['Measurement']}'";
   //            //                if (!empty($goodsItem['Weight']))
   //            //                    $fields[] = "weight = '{$goodsItem['Weight']}'";
   //            //                if (!empty($goodsItem['Volume']))
   //            //                    $fields[] = "volume = '{$goodsItem['Volume']}'";
   //            //                if (!empty($goodsItem['Description']))
   //            //                    $fields[] = "note = '{$goodsItem['Description']}'";
   //            //                if (!empty($goodsItem['FullDescription']))
   //            //                    $fields[] = "text = '{$goodsItem['FullDescription']}'";
   //            //                if (!empty($goodsItem['MetaHeader']))
   //            //                    $fields[] = "title = '{$goodsItem['MetaHeader']}'";
   //            //                if (!empty($goodsItem['MetaKeywords']))
   //            //                    $fields[] = "keywords = '{$goodsItem['MetaKeywords']}'";
   //            //                if (!empty($goodsItem['MetaDescription']))
   //            //                    $fields[] = "description = '{$goodsItem['MetaDescription']}'";
   //            //                $sqlItem .= implode(",", $fields);
   //            //                $sqlItem .= ' WHERE id = ' . $goodsItem['Id'] . ';';
   //            //                $sql .= $sqlItem . "\n";
   //            //            }
   //            //            if ($sql)
   //            //                mysqli_multi_query($db_link, $sql);
   //            //        }
               DB::commit();
           } catch (Exception $e) {
               DB::rollBack();
               $this->error = "Не удаётся произвести импорт товаров!";
           }
       }
   */

    // Создать группу
    function createGroup(&$groups, $idParent, $name)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        foreach ($groups as $group) {
            if ($group['upid'] == $idParent && trim($group['name']) == trim($name))
                return $group['id'];
        }

        $u = new DB('shop_group', 'sg');
        $data["codeGr"] = Category::getUrl(strtolower(se_translite_url(trim($name))));
        $data["name"] = trim($name);
        if ($idParent)
            $data["upid"] = $idParent;
        $u->setValuesFields($data);
        $id = $u->save();

        $group = [];
        $group["id"] = $id;
        $group['name'] = trim($name);
        $group["codeGr"] = $data["codeGr"];
        $group['upid'] = $idParent;
        $groups[] = $group;

        return $id;
    }

    // Создать группу 53
    private function createGroup53(&$groups, $idParent, $name)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        foreach ($groups as $group) {
            if ($group['upid'] == $idParent && $group['name'] == $name)
                return $group['id'];
        }

        $u = new DB('shop_group', 'sg');
        $data["codeGr"] = Category::getUrl(strtolower(se_translite_url(trim($name))));
        $data["name"] = $name;
        $u->setValuesFields($data);
        $id = $u->save();

        $group = [];
        $group["id"] = $id;
        $group['name'] = $name;
        $group["codeGr"] = $data["codeGr"];
        $group['upid'] = $idParent;
        $groups[] = $group;

        Category::saveIdParent($id, $idParent);

        return $id;
    }

    public function getLabels($idProduct = null)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $idProduct = $idProduct ? $idProduct : $this->input["id"];
        $result = [];
        $labels = (new ProductLabel())->fetch();
        $u = new DB("shop_label_product");
        $u->select("id_label");
        $u->where("id_product = ?", $idProduct);
        $items = $u->getList();
        foreach ($labels as $label) {
            $isChecked = false;
            foreach ($items as $item)
                if ($isChecked = ($label["id"] == $item["idLabel"]))
                    break;
            $label["isChecked"] = $isChecked;
            $result[] = $label;
        }
        return $result;
    }

    private function saveLabels()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $labels = $this->input["labels"];
        $labelsNew = [];
        foreach ($labels as $label)
            if ($label["isChecked"])
                $labelsNew[] = $label;
        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $labelsNew,
                    array("table" => "shop_label_product", "key" => "id_product", "link" => "id_label"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить ярлыки товара!";
            throw new Exception($this->error);
        }
    }

}
