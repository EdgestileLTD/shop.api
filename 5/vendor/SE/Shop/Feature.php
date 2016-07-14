<?php

namespace SE\Shop;

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
}
