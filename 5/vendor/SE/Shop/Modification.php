<?php

namespace SE\Shop;

class Modification extends Base
{
    protected $tableName = "shop_modifications_group";
    protected $sortBy = "sort";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'smg.*, 
                GROUP_CONCAT(DISTINCT(CONCAT_WS("\t", sf.id, sgf.id, sf.name, sf.type)) SEPARATOR "\n") `values`',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'shop_group_feature sgf',
                    "condition" => 'smg.id = sgf.id_group'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_feature sf',
                    "condition" => 'sf.id = sgf.id_feature'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_modifications sm',
                    "condition" => 'sm.id_mod_group = smg.id'
                )
            )
        );
    }

}
