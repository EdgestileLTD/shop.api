<?php

namespace SE\Shop;

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/IOFactory.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';

use SE\DB as DB;
use SE\Exception;
use \PHPExcel as PHPExcel;
use \PHPExcel_Writer_Excel2007 as PHPExcel_Writer_Excel2007;

class Order extends Base // порядок
{
    protected $tableName = "shop_order";

    // поля для поиска
    protected $searchFields = [
        ["title" => "№ заказа", "field" => "id", "active" => true],
        ["title" => "Заказчик", "field" => "customer", "active" => true, "query" => ["p.last_name", "p.first_name", "p.sec_name"]],
        ["title" => "Телефон заказчика", "field" => "customer_phone", "active" => true],
        ["title" => "Примечание", "field" => "note"],
        ["title" => "Менеджер", "field" => "manager"]
    ];

    private $orderStatuses =
        [
            'Y' => 'Оплачен',
            'N' => 'Не оплачен',
            'A' => 'Предоплата',
            'K' => 'Кредит',
            'P' => 'Подарок',
            'W' => 'В ожидании',
            'C' => 'Возврат',
            'T' => 'Тест'
        ];

    private $deliveryStatuses =
        [
            'Y' => 'Доставлен',
            'N' => 'Не доставлен',
            'M' => 'В работе',
            'P' => 'Отправлен'
        ];

    // получить от компании
    public static function fetchByCompany($idCompany)
    {
        $order = new Order(array("filters" => array("field" => "idCompany", "value" => $idCompany)));
        return $order->fetch();
    }

    // проверить статус заказа
    public static function checkStatusOrder($idOrder, $paymentType = null)
    {
        $u = new DB('shop_order', 'so');
        $u->select('(SUM((st.price - IFNULL(st.discount, 0)) * st.count) - IFNULL(so.discount, 0) +
                IFNULL(so.delivery_payee, 0)) sum_order');
        $u->innerJoin('shop_tovarorder st', 'st.id_order = so.id');
        $u->where('so.id = ?', $idOrder);
        $u->groupBy('so.id');
        $result = $u->fetchOne();
        $sumOrder = $result["sumOrder"];

        $u = new DB('shop_order_payee', 'sop');
        $u->select('SUM(sop.amount) sum_payee, MAX(sop.date) date_payee');
        $u->where(' sop.id_order = ?', $idOrder);
        $result = $u->fetchOne();
        $sumPayee = $result['sumPayee'];
        $datePayee = $result['datePayee'];

        if ($sumPayee >= $sumOrder) {
            $u = new DB('shop_order', 'so');
            $data["status"] = "Y";
            $data["isDelete"] = "N";
            $data["datePayee"] = $datePayee;
            if ($paymentType)
                $data["paymentType"] = $paymentType;
            $data["id"] = $idOrder;
            $u->setValuesFields($data);
            $u->save();
        };
    }

    // получить настройки
    protected function getSettingsFetch()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        return array(
            "select" => 'so.*,
                DATE_FORMAT(so.date_order, "%d.%m.%Y") date_order_display,  
                IFNULL(c.name, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name)) customer, 
                IFNULL(c.phone, p.phone) customer_phone, IFNULL(c.email, p.email) customer_email,                 
                CONCAT_WS(" ",  pm.last_name, pm.first_name, pm.sec_name) manager,
                sdt.name delivery_name,
                (SELECT (SUM((sto.price-IFNULL(sto.discount, 0))*sto.count)-IFNULL(so.discount, 0) + IFNULL(so.delivery_payee, 0)) 
                    FROM shop_tovarorder sto WHERE sto.id_order = so.id) amount,
                sd.telnumber delivery_phone, sd.email delivery_email, sd.calltime delivery_call_time, 
                sd.address delivery_address, sd.postindex delivery_post_index,
                sp.name_payment name_payment_primary, spp.name_payment, sch.id_coupon id_coupon, sch.discount coupon_discount',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'person p',
                    "condition" => 'p.id = so.id_author'
                ),
                array(
                    "type" => "left",
                    "table" => 'company c',
                    "condition" => 'c.id = so.id_company'
                ),
                array(
                    "type" => "left",
                    "table" => 'person pm',
                    "condition" => 'pm.id = so.id_admin'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_order_payee sop',
                    "condition" => 'sop.id_order = so.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_payment spp',
                    "condition" => 'spp.id = sop.payment_type'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_payment sp',
                    "condition" => 'sp.id = so.payment_type'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_coupons_history sch',
                    "condition" => 'sch.id_order = so.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_delivery sd',
                    "condition" => 'sd.id_order = so.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_deliverytype sdt',
                    "condition" => 'sdt.id = so.delivery_type'
                )
            ),
            "aggregation" => array(
                "type" => "SUM",
                "field" => "amount",
                "name" => "totalAmount"
            ),
            "convertingValues" => array(
                "totalAmount",
                "amount"
            )
        );
    }

    protected function correctItemsBeforeFetch($items = [])
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        foreach ($items as &$item) {
            if (!empty($item['customerPhone']))
                $item['customerPhone'] = Contact::correctPhone($item['customerPhone']);
            $item["amount"] = number_format($item["amount"], 2, '.', ' ');
        }


        return $items;
    }

    protected function correctResultBeforeFetch($result)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result["totalAmount"] = number_format($result["totalAmount"], 2, '.', ' ');

        return $result;
    }

    // получить информацию по настройкам
    protected function getSettingsInfo()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        return array(
            "select" => 'so.*, IFNULL(c.name, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name)) customer, 
                IFNULL(c.phone, p.phone) customer_phone, IFNULL(c.email, p.email) customer_email,
                (SUM((sto.price-IFNULL(sto.discount, 0))*sto.count)-IFNULL(so.discount, 0)+IFNULL(so.delivery_payee, 0)) amount,
                sdt.name delivery_name, sdt.note delivery_note,
                sd.id_city, sd.name_recipient, 
                sd.telnumber, sd.email, sd.calltime, sd.address, sd.postindex,
                CONCAT_WS(" ",  pm.last_name, pm.first_name, pm.sec_name) manager, sp.name_payment,
                sdts.note delivery_note_add',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'person p',
                    "condition" => 'p.id = so.id_author'
                ),
                array(
                    "type" => "left",
                    "table" => 'company c',
                    "condition" => 'c.id = so.id_company'
                ),
                array(
                    "type" => "left",
                    "table" => 'person pm',
                    "condition" => 'pm.id = so.id_admin'
                ),
                array(
                    "type" => "inner",
                    "table" => 'shop_tovarorder sto',
                    "condition" => 'sto.id_order = so.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_deliverytype sdt',
                    "condition" => 'sdt.id = so.delivery_type'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_delivery sd',
                    "condition" => 'sd.id_order = so.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_deliverytype sdts',
                    "condition" => 'sdts.id = sd.id_subdelivery'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_payment sp',
                    "condition" => 'sp.id = so.payment_type'
                )
            )
        );
    }

    protected function getAddInfo()
    {
        /** Передать информацию на страницу
         * 1 передаем базовую валюту в JS
         */

        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $result = array();
        $this->result["amount"] = (real)$this->result["amount"];
        $this->result["dateOrder"] = date("d.m.Y", strtotime($this->result["dateOrder"]));

        $result["oldStatus"] = $this->result["status"];
        $result["oldDeliveryStatus"] = $this->result["deliveryStatus"];
        $result["items"] = $this->getOrderItems();
        $result['payments'] = $this->getPayments();
        $result['paymentsCount'] = count($result['payments']);
        $result['customFields'] = $this->getCustomFields($this->input["id"]);
        $result['paid'] = $this->getPaidSum();
        $result['surcharge'] = $this->result["amount"] - $result['paid'];

        $u = new DB('main', 'm'); // 1
        $u->select('mt.name, mt.title, mt.name_front');
        $u->innerJoin('money_title mt', 'm.basecurr = mt.name');
        $result['baseCurr'] = $u->fetchOne();
        unset($u);

        return $result;
    }

    // получить оплаченную сумму
    private function getPaidSum()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $idOrder = $this->result["id"];
        $u = new DB('shop_order_payee', 'sop');
        $u->select('SUM(amount) amount');
        $u->where("sop.id_order = ?", $idOrder);
        $result = $u->fetchOne();
        return (real)$result['amount'];
    }

    // получить ордера
    private function getOrderItems()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $idOrder = $this->result["id"];
        $u = new DB('shop_tovarorder', 'sto');
        $u->select("sto.*, sp.code, sp.id_group, sp.curr, sp.lang, sp.img, si.picture, sp.measure, sp.name price_name");
        $u->leftJoin('shop_price sp', 'sp.id=sto.id_price');
        $u->leftJoin('shop_img si', 'si.id_price=sto.id_price AND si.`default`=1');
        $u->where("id_order = ?", $idOrder);
        $u->groupBy('sto.id');
        $result = $u->getList();
        unset($u);
        $items = array();
        if (!empty($result)) {
            foreach ($result as $item) {
                if ($item['picture']) $item['img'] = $item['picture'];
                $product['id'] = $item['id'];
                $product['idPrice'] = $item['idPrice'];
                $product['code'] = $item['code'];
                $product['name'] = $item['nameitem'];
                $product['originalName'] = $item['priceName'];
                $product['article'] = $item['article'];
                $product['measurement'] = $item['measure'];
                $product['idsModifications'] = $item["modifications"];
                $product['idGroup'] = $item['id_group'];
                $product['price'] = (real)$item['price'];
                $product['count'] = (real)$item['count'];
                $product['bonus'] = (real)$item['bonus'];
                $product['discount'] = (real)$item['discount'];
                $product['license'] = $item['license'];
                $product['note'] = $item['commentary'];
                $product['curr'] = $this->result["curr"];
                $items[] = $product;
            }
        }
        return $items;

    }

    // получить
    public function fetch($isId = false)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if ($this->input['searchText'])
            $this->filters = null;
        return parent::fetch($isId);
    }

    // получить платежи
    private function getPayments()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $payment = new Payment();
        return $payment->fetchByOrder($this->input["id"]);
    }

    // получить пользовательские поля
    private function getCustomFields($idOrder)
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_userfields', 'su');
        $u->select("sou.id, sou.id_order, sou.value, su.id id_userfield, 
                    su.name, su.type, su.values, sug.id id_group, sug.name name_group");
        $u->leftJoin('shop_order_userfields sou', "sou.id_userfield = su.id AND id_order = {$idOrder}");
        $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
        $u->where('su.data = "order"');
        $u->groupBy('su.id');
        $u->orderBy('sug.sort');
        $u->addOrderBy('su.sort');
        $result = $u->getList();

        $groups = array();
        foreach ($result as $item) {
            $key = (int)$item["idGroup"];
            $group = key_exists($key, $groups) ? $groups[$key] : array();
            $group["id"] = $item["idGroup"];
            $group["name"] = empty($item["nameGroup"]) ? "Без категории" : $item["nameGroup"];
            if ($item['type'] == "date")
                $item['value'] = date('Y-m-d', strtotime($item['value']));
            if (!key_exists($key, $groups))
                $groups[$key] = $group;
            $groups[$key]["items"][] = $item;
        }
        return array_values($groups);
    }

    // правильные значения перед сохранением
    protected function correctValuesBeforeSave()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (empty($this->input["id"]))
            $this->input["dateOrder"] = date("Y-m-d");
        if (isset($this->input["dateOrder"]))
            $this->input["dateOrder"] = date("Y-m-d", strtotime($this->input["dateOrder"]));
        if (isset($this->input["idAdmin"]) && empty($this->input["idAdmin"]))
            $this->input["idAdmin"] = null;
        if ($this->isNew) {
            $t = new DB("main", "m");
            $t->select("m.basecurr");
            $result = $t->fetchOne();
            if (!empty($result["basecurr"]))
                $this->input["curr"] = $result["basecurr"];
        }

        return true;
    }

    // сохранить добавленную информацию
    protected function saveAddInfo()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        return $this->saveItems() && $this->saveDelivery() && $this->saveCustomFields() && $this->savePayments();
    }

    private function savePayments()
    {
        if (!isset($this->input["payments"]))
            return true;

        try {
            $payments = $this->input["payments"];
            writeLog($payments);

            return true;

        } catch (Exception $e) {

        }
    }

    protected function afterSave()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!$this->input['send'])
            return;

        if ($this->isNew)
            $codeMail = 'orderuser';
        else {
            $codeMail = 'orduserch';
            if ($this->input["deliveryStatus"] != $this->input["oldDeliveryStatus"]) {
                // отправлен
                if ($this->input["deliveryStatus"] == "P")
                    $codeMail = 'orderdelivP';
                // в работе
                if ($this->input["deliveryStatus"] == "M")
                    $codeMail = 'orderdelivM';
                // доставлен
                if ($this->input["deliveryStatus"] == "Y")
                    $codeMail = 'orderdelivY';
            }
            if ($this->input["status"] != $this->input["oldStatus"]) {
                if ($this->input["status"] == "Y")
                    $codeMail = 'payuser';
            }
        }
        $this->sendMail($codeMail, $this->input["id"]);
    }

    // сохранить элементы
    private function saveItems()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        try {

            $idOrder = $this->input["id"];
            $products = $this->input["items"];
            foreach ($products as $p)
                if ($p["id"]) {
                    if (!empty($idsUpdate))
                        $idsUpdate .= ',';
                    $idsUpdate .= $p["id"];
                }

            $o = new DB('shop_order', 'so');
            $o->add_field('nk', 'tinyint(1)', 0, 1);
            if ($this->input["status"] == 'N') {
                $o->setValuesFields(array('id' => $idOrder, 'nk' => 0));
                $o->save();
                $ua = new DB('se_user_account', 'sua');
                $ua->where('order_id = ?', $idOrder);
                $ua->deleteList();
            }

            DB::query("UPDATE shop_price sp
                INNER JOIN shop_tovarorder st ON sp.id = st.id_price
                SET sp.presence_count = sp.presence_count + st.count
                WHERE st.id_order = ({$idOrder}) AND sp.presence_count IS NOT NULL AND sp.presence_count >= 0");
            DB::query("UPDATE shop_modifications sm
                INNER JOIN shop_tovarorder st ON sm.id IN (st.modifications)
                INNER JOIN shop_price sp ON sp.id = st.id_price
                SET sm.count = sm.count + st.count
                WHERE st.id_order = ({$idOrder}) AND sm.count IS NOT NULL AND sm.count >= 0");

            $u = new DB('shop_tovarorder', 'st');
            if (!empty($idsUpdate))
                $u->where('NOT `id` IN (' . $idsUpdate . ') AND id_order = ?', $idOrder)->deleteList();
            else $u->where('id_order = ?', $idOrder)->deleteList();

            // новый товары/услуги заказа
            foreach ($products as $p) {
                if (!$p["id"]) {
                    $data[] = array('id_order' => $idOrder, 'id_price' => $p["idPrice"], 'article' => $p["article"],
                        'nameitem' => $p["name"], 'price' => (float)$p["price"],
                        'discount' => $p["discount"], 'count' => $p["count"], 'modifications' => $p["idsModifications"],
                        'license' => $p["license"], 'commentary' => $p["note"], 'action' => $p["action"]);
                } else {
                    $u = new DB('shop_tovarorder', 'sto');
                    $u->select("modifications");
                    $u->where("id = ?", $p["id"]);
                    $result = $u->fetchOne();
                    if ($result["modifications"])
                        $p["idsModifications"] = $result["modifications"];
                }
                if ($p["idPrice"] && $p["count"] > 0) {
                    DB::query("UPDATE shop_price SET presence_count = presence_count - '{$p["count"]}'
                        WHERE id = {$p["idPrice"]} AND presence_count IS NOT NULL AND presence_count >= 0");
                }
                if ($p["idsModifications"] && $p["idPrice"]) {
                    if ($p["count"] > 0)
                        DB::query("UPDATE shop_modifications
                            SET count = count  - '{$p["count"]}'
                            WHERE id IN ({$p["idsModifications"]}) AND count IS NOT NULL AND count >= 0 AND id_price = {$p["idPrice"]}");
                }
            }
            if (!empty($data))
                DB::insertList('shop_tovarorder', $data);

            // обновление товаров/услугов заказа
            foreach ($products as $p)
                if ($p["id"]) {
                    $p["nameitem"] = $p["name"];
                    $p["modifications"] = $p["idsModifications"];
                    $u = new DB('shop_tovarorder', 'st');
                    $u->setValuesFields($p);
                    $u->save();
                }

            return true;

        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить товары и услуги заказа!";
        }

    }

    // сохранить пользовательские поля
    private function saveCustomFields()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if (!isset($this->input["customFields"]))
            return true;

        try {
            $idOrder = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $field["idOrder"] = $idOrder;
                $u = new DB('shop_order_userfields', 'cu');
                $u->setValuesFields($field);
                $u->save();
            }
            return true;

        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о заказе!";
            throw new Exception($this->error);
        }
    }

    // сохранить доставку
    private function saveDelivery()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');

        try {
            $input = $this->input;
            unset($input["ids"]);
            $idOrder = $input["id"];
            $p = new DB('shop_delivery', 'sd');
            $p->select("id");
            $p->where('id_order = ?', $idOrder);
            $result = $p->fetchOne();
            if ($result["id"])
                $input["id"] = $result["id"];
            $u = new DB('shop_delivery', 'sd');
            $u->setValuesFields($input);
            $u->save();

            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доставку заказа!";
        }
    }

    // удалить
    public function delete()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        try {
            $input = $this->input;
            if (!empty($input["allMode"]))
                DB::query("UPDATE shop_order SET is_delete = 'Y'");
            else {
                $ids = implode(",", $input["ids"]);
                if (!empty($ids))
                    DB::query("UPDATE shop_order SET is_delete = 'Y' WHERE id IN ({$ids})");

            }
        } catch (Exception $e) {
            $this->error = "Не удаётся отменить заказ!";
        }
    }

    public function export()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        if ($this->input["id"]) {
            $this->exportItem();
            return;
        }

        $fileName = "export_orders.xlsx";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath, 0777, true);
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";

        $xls = new PHPExcel();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();
        $sheet->setTitle("Заказы");

        $sheet->setCellValue("A1", "№");
        $sheet->setCellValue("B1", "Дата");
        $sheet->setCellValue("C1", "Закачзик");
        $sheet->setCellValue("D1", "Телефон");
        $sheet->setCellValue("E1", "Сумма");
        $sheet->setCellValue("F1", "Доставка");
        $sheet->setCellValue("G1", "Индекс");
        $sheet->setCellValue("H1", "Адрес");
        $sheet->setCellValue("I1", "Телефон дост.");
        $sheet->setCellValue("K1", "Время звонка");
        $sheet->setCellValue("L1", "Примечание");


        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(35);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('K')->setWidth(30);
        $sheet->getColumnDimension('L')->setWidth(40);

        $this->limit = null;
        $this->sortOrder = "asc";
        $orders = $this->fetch();
        $i = 2;
        foreach ($orders as $order) {
            $sheet->setCellValue("A$i", $order["id"]);
            $sheet->setCellValue("B$i", $order["dateOrderDisplay"]);
            $sheet->setCellValue("C$i", $order["customer"]);
            $sheet->setCellValue("D$i", $order["customerPhone"]);
            $sheet->setCellValue("E$i", $order["amount"]);
            $sheet->setCellValue("F$i", $order["deliveryName"]);
            $sheet->setCellValue("G$i", $order["deliveryIndex"]);
            $sheet->setCellValue("H$i", $order["deliveryAddress"]);
            $sheet->setCellValue("I$i", $order["deliveryPhone"]);
            $sheet->setCellValue("K$i", $order["deliverCallTime"]);
            $sheet->setCellValue("L$i", $order["deliverNote"]);

            $sheet->getStyle("E$i")->getNumberFormat()->setFormatCode('#,##0.00');

            $i++;
        }

        $objWriter = new PHPExcel_Writer_Excel2007($xls);
        $objWriter->save($filePath);

        $this->result["url"] = $urlFile;
        $this->result["name"] = $fileName;

    }

    private function exportItem()
    {
        $this->debugging('funct', __FUNCTION__ . ' ' . __LINE__, __CLASS__, '[comment]');
        $fileName = "export_order_{$this->input["id"]}.xlsx";
        $filePath = DOCUMENT_ROOT . "/files";
        if (!file_exists($filePath) || !is_dir($filePath))
            mkdir($filePath, 0777, true);
        $filePath .= "/{$fileName}";
        $urlFile = 'http://' . HOSTNAME . "/files/{$fileName}";


        $order = $this->info();

        $xls = new PHPExcel();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();
        $sheet->setTitle('Заказ № ' . $order["id"]);

        $sheet->setCellValue("A1", 'Заказ № ' . $order["id"] . " от " . date("d.m.Y", strtotime($order["dateOrder"])));
        $sheet->getStyle('A1')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);
        $sheet->getStyle('A1')->getFill()->getStartColor()->setRGB('EEEEEE');
        $sheet->getStyle('A1')->getFont()->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A1:F1');
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(9);
        $sheet->getColumnDimension('E')->setWidth(9);
        $sheet->getColumnDimension('F')->setWidth(9);

        $sheet->setCellValue("A3", '№ счёта:');
        if ($order["payments"])
            $sheet->setCellValue("B3", $order["payments"][0]["docNum"]);
        $sheet->setCellValue("A4", 'Дата заказа:');
        $sheet->setCellValue("B4", date("d.m.Y", strtotime($order["dateOrder"])));
        $sheet->setCellValue("C4", 'Статус заказа:');
        $sheet->setCellValue("D4", $this->orderStatuses[$order["status"]]);
        $sheet->mergeCells('D4:F4');
        $sheet->setCellValue("A5", 'Заказчик:');
        $sheet->setCellValue("B5", $order["customer"]);
        $sheet->setCellValue("A6", 'Телефон:');
        $sheet->setCellValue("B6", $order["customerPhone"]);
        $sheet->setCellValue("C6", 'Email:');
        $sheet->setCellValue("D6", $order["customerEmail"]);
        $sheet->mergeCells('D6:F6');
        $sheet->setCellValue("A7", 'Доставка:');
        $sheet->setCellValue("B7", $order["deliveryName"]);
        $sheet->setCellValue("C7", 'Сумма:');
        $sheet->setCellValue("D7", number_format($order["deliveryPayee"], 2, ',', ' '));
        $sheet->mergeCells('D7:F7');
        $sheet->setCellValue("A8", 'Статус:');
        $sheet->setCellValue("B8", $this->deliveryStatuses[$order["deliveryStatus"]]);
        $sheet->setCellValue("C8", 'Дата доставки:');
        if (!empty($order["deliveryDate"]))
            $sheet->setCellValue("D8", date("d.m.Y", strtotime($order["deliveryDate"])));
        $sheet->mergeCells('D8:F8');
        $sheet->setCellValue("A9", 'Адрес доставки:');
        $sheet->setCellValue("B9", $order["address"]);
        $sheet->getStyle('B9')->getAlignment()->setWrapText(true);
        $sheet->setCellValue("C9", 'Индекс:');
        $sheet->setCellValue("D9", $order["postindex"]);
        $sheet->mergeCells('D9:F9');
        $sheet->setCellValue("A10", 'Телефон:');
        $sheet->setCellValue("B10", $order["telnumber"]);
        $sheet->setCellValue("C10", 'Время звонка:');
        $sheet->setCellValue("D10", $order["calltime"]);
        $sheet->mergeCells('D10:F10');
        $sheet->setCellValue("A11", 'Примечание:');
        $sheet->setCellValue("B11", $order["deliveryNoteAdd"]);
        $sheet->mergeCells('B11:F11');
        $sheet->setCellValue("C12", 'Сумма товаров и услуг:');
        $sheet->mergeCells('C12:D12');
        $sheet->setCellValue("E12", number_format($order["amount"] + $order["discount"] - $order["deliveryPayee"], 2, ',', ' '));
        $sheet->mergeCells('E12:F12');
        $sheet->setCellValue("C13", 'Сумма скидки:');
        $sheet->mergeCells('C13:D13');
        $sheet->setCellValue("E13", number_format($order["discount"], 2, ',', ' '));
        $sheet->mergeCells('E13:F13');
        $sheet->setCellValue("C14", 'ИТОГО:');
        $sheet->mergeCells('C14:D14');
        $sheet->setCellValue("E14", number_format($order["amount"], 2, ',', ' '));
        $sheet->mergeCells('E14:F14');
        $sheet->getStyle('D7')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E12')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E13')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E14')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A5:F5')->getBorders()->getTop()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THICK);
        $sheet->getStyle('A7:F7')->getBorders()->getTop()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THICK);
        $sheet->getStyle('A12:F12')->getBorders()->getTop()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THICK);
        $sheet->getStyle('A9:F9')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_TOP);
        $sheet->getStyle('A3:A15')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C3:C15')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('B3:B15')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('D3:D15')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('E3:E15')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('A3:A11')->getFont()->setBold(true);
        $sheet->getStyle('C3:C11')->getFont()->setBold(true);
        $sheet->getStyle('C14:F14')->getFont()->setBold(true);
        $sheet->getStyle('C12:F14')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);


        $sheet->setCellValue("A17", 'Артикул');
        $sheet->setCellValue("B17", 'Наименование товара');
        $sheet->mergeCells('B17:C17');
        $startSym = "D";
        $codeSym = ord($startSym);
        if ($order["items"]) {
            $product = $order["items"][0];
            foreach ($product["modifications"] as $modification)
                $sheet->setCellValue(chr($codeSym++) . "17", $modification["name"]);
        }

        $startSymCount = $codeSym;
        $sheet->setCellValue(chr($codeSym++) . "17", 'Кол-во');
        $sheet->setCellValue(chr($codeSym++) . "17", 'Цена');
        $sheet->setCellValue(chr($codeSym) . "17", 'Сумма');
        $sheet->setCellValue("A16", 'Товары и услуги заказа');
        $endSym = chr($codeSym);
        $sheet->mergeCells('A16:' . $endSym . '16');
        $sheet->getStyle('A16:' . $endSym . '16')->getBorders()->getBottom()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
        $sheet->getStyle('A16:' . $endSym . '16')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A17:' . $endSym . '17')->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
        $sheet->getStyle('A17:' . $endSym . '17')->getFont()->setBold(true);
        $i = 18;
        foreach ($order["items"] as $product) {
            $codeSym = ord($startSym);
            $sheet->getStyle("E$i:" . $endSym . $i)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A$i:" . $endSym . $i)->getBorders()->getAllBorders()->setBorderStyle(\PHPExcel_Style_Border::BORDER_THIN);
            $sheet->getStyle("A$i:" . $endSym . $i)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_TOP);
            $sheet->mergeCells("B$i:C$i");
            $sheet->getStyle("B$i")->getAlignment()->setWrapText(true);
            $sheet->setCellValue("A$i", $product["article"]);
            if (strlen($product["originalName"]) > 50)
                $sheet->getRowDimension("$i")->setRowHeight(30);
            $sheet->setCellValue("B$i", $product["originalName"]);
            foreach ($product["modifications"] as $modification) {
                $sheet->setCellValue(chr($codeSym++) . $i, (string)$modification["value"]);
                $sheet->getStyle(chr($codeSym) . $i . ':' . chr($codeSym) . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
            }
            $codeSym = $startSymCount;
            $sheet->setCellValue(chr($codeSym++) . "$i", $product["count"]);
            $sheet->setCellValue(chr($codeSym++) . "$i", number_format($product["price"] - $product["discount"], 2, ',', ' '));
            $sheet->setCellValue(chr($codeSym) . "$i", number_format(($product["price"] - $product["discount"]) * $product["count"], 2, ',', ' '));
            $i++;
        }
        foreach (range('A', $endSym) as $columnID)
            $sheet->getColumnDimension($columnID)->setAutoSize(true);

        $objWriter = new PHPExcel_Writer_Excel2007($xls);
        $objWriter->save($filePath);

        $this->result["url"] = $urlFile;
        $this->result["name"] = $fileName;

    }

}
