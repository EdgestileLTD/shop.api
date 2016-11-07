<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Order extends Base
{
    protected $tableName = "shop_order";

    public static function fetchByCompany($idCompany)
    {
        return (new Order(array("filters" => array("field" => "idCompany", "value" => $idCompany))))->fetch();
    }

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

    protected function getSettingsFind()
    {
        return array('so.id', 'so.date_order', 'so.date_payee', 'so.serial', 'so.commentary');
    }

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'so.*, IFNULL(c.name, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name)) customer, 
                IFNULL(c.phone, p.phone) customer_phone, IFNULL(c.email, p.email) customer_email, 
                (SUM((sto.price-IFNULL(sto.discount, 0))*sto.count)-IFNULL(so.discount, 0) + IFNULL(so.delivery_payee, 0)) amount, 
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
                    "type" => "inner",
                    "table" => 'shop_tovarorder sto',
                    "condition" => 'sto.id_order = so.id'
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
                )
            ),
            "aggregation" => array(
                "type" => "SUM",
                "field" => "amount",
                "name" => "totalAmount"
            )
        );
    }

    protected function getSettingsInfo()
    {
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
        $result = [];
        $this->result["amount"] = (real)$this->result["amount"];
        $result["items"] = $this->getOrderItems();
        $result['payments'] = $this->getPayments();
        $result['customFields'] = $this->getCustomFields($this->input["id"]);
        $result['paid'] = $this->getPaidSum();
        $result['surcharge'] = $this->result["amount"] - $result['paid'];
        return $result;
    }

    private function getPaidSum()
    {
        $idOrder = $this->result["id"];
        $u = new DB('shop_order_payee', 'sop');
        $u->select('SUM(amount) amount');
        $u->where("sop.id_order = ?", $idOrder);
        $result = $u->fetchOne();
        return (real)$result['amount'];
    }

    private function getOrderItems()
    {
        $idOrder = $this->result["id"];
        $u = new DB('shop_tovarorder', 'sto');
        $u->select("sto.*, sp.code, sp.id_group, sp.curr, sp.lang, sp.img, si.picture, sp.measure, sp.name price_name");
        $u->leftJoin('shop_price sp', 'sp.id=sto.id_price');
        $u->leftJoin('shop_img si', 'si.id_price=sto.id_price AND si.`default`=1');
        $u->where("id_order = ?", $idOrder);
        $u->groupBy('sto.id');
        $result = $u->getList();
        unset($u);
        $items = [];
        if (!empty($result)) {
            foreach ($result as $item) {
                if ($item['picture']) $item['img'] = $item['picture'];
                $product['id'] = $item['id'];
                $product['idPrice'] = $item['id_price'];
                $product['code'] = $item['code'];
                $product['name'] = $item['nameitem'];
                $product['originalName'] = $item['price_name'];
                //$product['modifications'] = getModifications($item);
                $product['article'] = $item['article'];
                $product['measurement'] = $item['measure'];
                $product['idGroup'] = $item['id_group'];
                $product['price'] = (real)$item['price'];
                $product['count'] = (real)$item['count'];
                $product['bonus'] = (real)$item['bonus'];
                $product['discount'] = (real)$item['discount'];
                $product['license'] = $item['license'];
                $product['note'] = $item['commentary'];
                $items[] = $product;
            }
        }
        return $items;

    }

    private function getPayments()
    {
        return (new Payment())->fetchByOrder($this->input["id"]);
    }

    private function getCustomFields($idOrder)
    {
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

        $groups = [];
        foreach ($result as $item) {
            $key = (int)$item["idGroup"];
            $group = key_exists($key, $groups) ? $groups[$key] : [];
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

    protected function correctValuesBeforeSave()
    {
        if (empty($this->input["id"]))
            $this->input["dateOrder"] = date("Y-m-d");
        return true;
    }

    protected function saveAddInfo()
    {
        $this->saveItems();
        $this->saveDelivery();
        $this->savePayments();
        $this->saveCustomFields();
        return true;
    }

    private function saveItems()
    {
        $idOrder = $this->input["id"];
        $products = $this->input["items"];
        foreach ($products as $p)
            if ($p["id"]) {
                if (!empty($idsUpdate))
                    $idsUpdate .= ',';
                $idsUpdate .= $p["id"];
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
                $u = new DB('shop_tovarorder', 'st');
                $u->setValuesFields($p);
                $u->save();
            }
    }

    private function saveCustomFields()
    {
        if (!isset($this->input["customFields"]))
            return true;

        try {
            $idOrder = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = [];
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

    private function saveDelivery()
    {
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
    }

    private function savePayments()
    {
        $payments = $this->input["payments"];
    }

    public function delete()
    {
        try {
            $input = $this->input;
            $input["isDelete"] = "Y";
            $u = new DB('shop_order', 'so');
            $u->setValuesFields($input);
            $u->save();
        } catch (Exception $e) {
            $this->error = "Не удаётся отменить заказ!";
        }
    }

}
