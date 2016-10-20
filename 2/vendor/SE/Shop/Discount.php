<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Discount extends Base
{
    protected $tableName = "shop_discounts";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sd.*',
            "left" => array(
                "type" => "inner",
                "table" => 'shop_discount_links sdl',
                "condition" => 'sdl.discount_id = sd.id'
            )
        );
    }

    protected function getAddInfo()
    {
        $result["listGroupsProducts"] = $this->getListGroupsProducts($this->result["id"]);
        $result["listProducts"] = $this->getListProducts($this->result["id"]);
        $result['listContacts'] = $this->getListContacts($this->result["id"]);
        return $result;
    }

    protected function saveAddInfo()
    {
        return $this->saveProducts() && $this->saveGroupsProducts() && $this->saveContacts();
    }

    private function getListProducts($id) {
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr');
            $u->innerJoin("shop_price sp", "sdl.id_price = sp.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sp.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список товаров скидки!";
        }
    }

    private function getListGroupsProducts($id) {
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('sg.id, sg.code_gr, sg.name');
            $u->innerJoin("shop_group sg", "sdl.id_group = sg.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sg.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп товаров скидки!";
        }
    }

    private function getListContacts($id) {
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('p.id, p.first_name, p.sec_name, p.last_name, p.email');
            $u->innerJoin("person p", "sdl.id_user = p.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("p.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список контактов скидки!";
        }
    }

    private function saveProducts()
    {
        if (!isset($this->input["listProducts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["listProducts"],
                    array("table" => "shop_discount_links", "key" => "discount_id", "link" => "id_price"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить товары для скидки!";
            throw new Exception($this->error);
        }

    }

    private function saveGroupsProducts()
    {
        return true;
    }

    private function saveContacts()
    {
        return true;
    }

}
