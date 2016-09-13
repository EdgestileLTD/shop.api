<?php

namespace SE\Shop;

class PreOrder extends Base
{
    protected $tableName = "shop_preorder";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sp.*, pr.name product',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'shop_price pr',
                    "condition" => 'pr.id = sp.id_price'
                )
            )
        );
    }
}
