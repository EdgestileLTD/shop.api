<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Feature extends Base
{
    protected $tableName = "shop_feature";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sf.*, sfg.name name_group',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'shop_feature_group sfg',
                    "condition" => 'sfg.id = sf.id_feature_group'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_group_feature sgf',
                    "condition" => 'sgf.id_feature = sf.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_modifications_group smg',
                    "condition" => 'sgf.id_group = smg.id'
                )
            )
        );
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    private function getValues()
    {
        $fvalues = new FeatureValue();

        return $fvalues->fetchByIdFeature($this->input["id"]);
    }

    protected function getAddInfo()
    {
        $result["values"] = $this->getValues();
        return $result;
    }

    private function saveValues()
    {
        if (!isset($this->input["values"]))
            return;

        try {
            $idFeature = $this->input["id"];
            $values = $this->input["values"];
            $idsStore = "";
            foreach ($values as $value) {
                if ($value["id"] > 0) {
                    if (!empty($idsStore))
                        $idsStore .= ",";
                    $idsStore .= $value["id"];
                    $u = new DB('shop_feature_value_list');
                    $u->setValuesFields($value);
                    $u->save();
                }
            }

            if (!empty($idsStore)) {
                $u = new DB('shop_feature_value_list');
                $u->where("id_feature = {$idFeature} AND NOT (id IN (?))", $idsStore)->deleteList();
            } else {
                $u = new DB('shop_feature_value_list');
                $u->where("id_feature = ?", $idFeature)->deleteList();
            }

            $data = [];
            foreach ($values as $value)
                if (empty($value["id"]) || ($value["id"] <= 0)) {
                    $data[] = array('id_feature' => $idFeature, 'value' => $value["value"], 'color' => $value["color"],
                        'sort' => (int) $value["sort"], 'image' => $value["image"]);
                }
            if (!empty($data))
                DB::insertList('shop_feature_value_list', $data);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить значения параметра!";
            throw new Exception($this->error);
        }
    }

    public function saveAddInfo()
    {
        $this->saveValues();
        return true;
    }
}
