<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ProductType extends Base
{
    protected $tableName = "shop_product_type";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'spt.*, GROUP_CONCAT(sf.name SEPARATOR \'; \') features',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'shop_product_type_feature sptf',
                    "condition" => 'sptf.id_type = spt.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_feature sf',
                    "condition" => 'sptf.id_feature = sf.id'
                )
            )
        );
    }

    private function getFeatures() {

        try {
            $id = $this->input["id"];

            $u = new DB('shop_product_type_feature', 'sptf');
            $u->select("sptf.id_feature id, sf.name, sf.type, sf.sort, sf.measure");
            $u->innerJoin('shop_feature sf', 'sf.id = sptf.id_feature');
            $u->groupBy("sf.id");
            $u->where('sptf.id_type = ?', $id);
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список параметров типа!";
        }
    }

    public function items() {
        try {
            $id = $this->input["id"];

            $u = new DB('shop_product_type_feature', 'sptf');
            $u->select("sptf.id, sptf.id_feature, sf.name, sf.type, sf.sort, sf.measure");
            $u->innerJoin('shop_feature sf', 'sf.id = sptf.id_feature');
            $u->groupBy("sf.id");
            $u->where('sptf.id_type = ?', $id);
            $this->result["features"] = $u->getList();
            return $this->result;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список параметров типа!";
        }
    }

    protected function getAddInfo()
    {
        $result["features"] = $this->getFeatures();
        return $result;
    }

    protected function saveFeatures()
    {
        if (!isset($this->input["features"]))
            return true;

        try {
            DB::saveManyToMany($this->input["id"], $this->input["features"],
                array("table" => "shop_product_type_feature", "key" => "id_type", "link" => "id_feature"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить параметры типа!";
            throw new Exception($this->error);
        }
    }

    protected function saveAddInfo()
    {
        if (!$this->input["id"])
            return false;

        return $this->saveFeatures();
    }

}