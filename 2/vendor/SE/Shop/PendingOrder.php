<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class PendingOrder extends Base
{

    protected $tableName = "shop_stat_contact";

    private $contactFields = array(
        "contact_email" => "email",
        "contact_name" => "name",
        "contact_phone" => "phone"
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

    public function info()
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
                $item["cardGoods"] = $this->getCartGoods($this->input["id"]);
                $item = $this->parseData($row["data"], $item);
                $items[] = $item;
            }
            $this->result = $items[0];
            return $this->result;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию об объекте!";
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

    }

    private function getCartGoods($id)
    {

    }
}
