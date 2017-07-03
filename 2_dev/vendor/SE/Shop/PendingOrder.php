<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class PendingOrder extends Base
{

    protected $tableName = "shop_stat_session";

    private $contactFields = array(
        "contact_email" => "email",
        "contact_name" => "name",
        "contact_phone" => "phone"
    );

    private $eventsDisplay = array(
        "show product" => "просмотр товара",
        "add cart" => "добавили товар в корзину",
        "view shopcart" => "перешли на страницу корзины",
        "input contact" => "заполнили поле данных",
        "select delivery" => "выбрали/изменили способ доставки",
        "select payment" => "выбрали/изменили способ оплаты",
        "place order" => "оформили заказ (нажали кнопку \"Оформить\")",
        "confirm order" => "подтвердили заказ"
    );

    public function fetch()
    {
        try {
            $u = new DB('shop_stat_contact', 'ssc');
            $u->select("ssc.id_session, ssc.created_at, 
                GROUP_CONCAT(CONCAT(ssc.contact, '##', ssc.value, '##', ssc.created_at) SEPARATOR '\t') data,
                COUNT(DISTINCT(ssc1.id_product)) count_cart, COUNT(DISTINCT(ssv.id_product)) count_view");
            $u->leftJoin("shop_stat_cart ssc1", "ssc.id_session = ssc1.id_session");
            $u->leftJoin("shop_stat_viewgoods ssv", "ssc.id_session = ssv.id_session");
            $u->orderBy("created_at", true);
            $u->groupBy("id_session");
            $items = array();
            $rows = $u->getList($this->limit, $this->offset);
            foreach ($rows as $row) {
                $item = array();
                $item["id"] = $row["idSession"];
                $item["idSession"] = $row["idSession"];
                $item["createdAt"] = $row["createdAt"];
                $item["countCart"] = $row["countCart"];
                $item["countView"] = $row["countView"];
                $item = $this->parseData($row["data"], $item);
                $items[] = $item;
            }
            $this->result["items"] = $items;
            $this->result["count"] = $u->getListCount();

        } catch (Exception $e) {
            $this->error = "Не удаётся получить список объектов!";
        }
    }

    public function info($id = NULL)
    {
        try {

            $u = new DB('shop_stat_contact', 'ssc');
            $u->select("ssc.id_session, ssc.created_at, 
                GROUP_CONCAT(CONCAT(ssc.contact, '##', ssc.value, '##', ssc.created_at) SEPARATOR '\t') data");
            $u->orderBy("created_at", true);
            $u->groupBy("id_session");
            $u->where("ssc.id_session = ?", $this->input["id"]);
            $items = array();
            $rows = $u->getList();
            foreach ($rows as $row) {
                $item = array();
                $item["idSession"] = $row["idSession"];
                $item["createdAt"] = $row["createdAt"];
                $item["events"] = $this->getEvents($this->input["id"]);
                $item["viewGoods"] = $this->getViewGoods($this->input["id"]);
                $item["cartGoods"] = $this->getCartGoods($this->input["id"]);
                $item = $this->parseData($row["data"], $item);
                $items[] = $item;
            }
            $this->result = $items[0];
            return $this->result;
        } catch (Exception $e) {
            $this->error = empty($this->error) ? "Не удаётся получить информацию об объекте!" : $this->error;
        }
    }

    private function parseData($dataIn, $item)
    {
        $dataList = explode("\t", $dataIn);
        $values = array();
        foreach ($dataList as $data) {
            $valuesTemp = explode("##", $data);
            if (!key_exists($valuesTemp[0], $values)) {
                $values[$valuesTemp[0]] = $valuesTemp;
                continue;
            } elseif (strtotime($values[$valuesTemp[0]][2]) < strtotime($valuesTemp[2]))
                $values[$valuesTemp[0]] = $valuesTemp;
        }
        $data = array();
        foreach ($values as $key => $value) {
            if (key_exists($key, $this->contactFields))
                $item[$this->contactFields[$key]] = $value[1];
            else $data[] = "{$key}: {$value[1]}";
        }
        if ($data)
            $item["data"] = implode(", ", $data);
        return $item;
    }

    private function getEvents($id)
    {
        try {
            $u = new DB('shop_stat_events', 'sse');
            $u->select('sse.*, 
                CASE WHEN sp.name IS NOT NULL THEN CONCAT("Товар #", sp.id, ". Наименование: " , sp.name) 
                     WHEN so.id IS NOT NULL THEN CONCAT("Заказ #", so.id, " от " , so.date_order)
                     WHEN p.id IS NOT NULL THEN CONCAT("Платеж. система #", p.id, ". " , p.name_payment)                     
                     WHEN sdt.id IS NOT NULL THEN CONCAT("Тип доставки #", sdt.id, ". " , sdt.name)
                     ELSE "Информация отсутствует" END 
                 AS content');
            $u->leftJoin('shop_price sp', 'sse.content = sp.id AND (sse.event LIKE "%product%" OR sse.event LIKE "%cart%")');
            $u->leftJoin('shop_order so', 'sse.content = sp.id AND sse.event LIKE "%order%"');
            $u->leftJoin('shop_payment p', 'sse.content = p.id AND sse.event LIKE "%pay%"');
            $u->leftJoin('shop_deliverytype sdt', 'sse.content = sdt.id AND sse.event LIKE "%delivery%"');
            $u->where("sse.id_session = ?", $id);
            $u->orderBy("sse.created_at");
            $items = array();
            $rows = $u->getList();
            foreach ($rows as $row) {
                $row["eventDisplay"] = key_exists($row["event"], $this->eventsDisplay) ?
                    $this->eventsDisplay[$row["event"]] : $row["event"];
                $items[] = $row;
            }
            return $items;

        } catch (Exception $e) {
            $this->error = "Не удаётся получить список событий!";
        }
    }

    private function getViewGoods($id)
    {
        try {
            $u = new DB('shop_stat_viewgoods', 'ssv');
            $u->select('ssv.*, IFNULL(sp.name, "Товар удален") name_product');
            $u->leftJoin('shop_price sp', 'ssv.id_product = sp.id');
            $u->where("ssv.id_session = ?", $id);
            $u->orderBy("ssv.created_at");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список просмотренных товаров!";
        }
    }

    private function getCartGoods($id)
    {
        try {
            $u = new DB('shop_stat_cart', 'ssс');
            $u->select('ssс.*, IFNULL(sp.name, "Товар удален") name_product');
            $u->leftJoin('shop_price sp', 'ssс.id_product = sp.id');
            $u->where("ssс.id_session = ?", $id);
            $u->orderBy("ssс.created_at");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о корзине!";
        }
    }
}
