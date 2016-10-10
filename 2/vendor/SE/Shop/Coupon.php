<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Coupon extends Base
{
    protected $tableName = "shop_coupons";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sc.*, CONCAT_WS(" ",  p.last_name, p.first_name, p.sec_name) as user_name',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'person p',
                    "condition" => 'p.id = sc.id_user'
                )
            )
        );
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    public function getProducts($idCoupon = null)
    {
        $id = $idCoupon ? $idCoupon : $this->input["id"];
        if (!$id)
            return [];

        $u = new DB('shop_coupons_goods', 'scg');
        $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr, sp.measure, sp.presence_count');
        $u->innerJoin("shop_price sp", "scg.price_id = sp.id");
        $u->where("scg.coupon_id = ?", $id);
        $u->groupBy("sp.id");
        return $u->getList();
    }

    public function getGroups($idCoupon = null)
    {
        $id = $idCoupon ? $idCoupon : $this->input["id"];
        if (!$id)
            return [];

        $u = new DB('shop_coupons_goods', 'scg');
        $u->select('sg.id, sg.name');
        $u->innerJoin("shop_group sg", "scg.group_id = sg.id");
        $u->where("scg.coupon_id = ?", $id);
        $u->groupBy("sg.id");
        return $u->getList();
    }
    
    public function getOrders($idCoupon = null)
    {
        $id = $idCoupon ? $idCoupon : $this->input["id"];
        if (!$id)
            return [];

        return (new Order(array("filters" => array("field" => "idCoupon", "value" => $id))))->fetch();
    }

    protected function getAddInfo()
    {
        $result["products"] = $this->getProducts();
        $result["groups"] = $this->getGroups();
        $result["orders"] = $this->getOrders();
        return $result;
    }

    public function saveGroups()
    {
        try {            
            DB::saveManyToMany($this->input["id"], $this->input["groups"],
                array("table" => "shop_coupons_goods", "key" => "coupon_id", "link" => "group_id"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить назначенные группы товаров для купона!";
            throw new Exception($this->error);
        }
    }

    public function saveProducts()
    {
        try {            
            DB::saveManyToMany($this->input["id"], $this->input["products"],
                array("table" => "shop_coupons_goods", "key" => "coupon_id", "link" => "price_id"));
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить назначенные товары для купона!";
            throw new Exception($this->error);
        }
    }

    protected function saveAddInfo()
    {
        $this->saveGroups();
        $this->saveProducts();

        return true;
    }


}
