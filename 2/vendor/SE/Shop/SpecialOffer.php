<?php

namespace SE\Shop;

class SpecialOffer extends Base
{
    protected $tableName = "shop_leader";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sp.id, sp.article, sp.code, sp.name, sp.price',
            "joins" => array(
                "type" => "inner",
                "table" => 'shop_price sp',
                "condition" => 'sp.id = sl.id_price'
            )
        );
    }
}