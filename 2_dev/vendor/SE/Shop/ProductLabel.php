<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ProductLabel extends Base
{
    protected $tableName = "shop_label";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sl.*',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'shop_label_product spl',
                    "condition" => 'spl.id_label = sl.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_price sp',
                    "condition" => 'sp.id = spl.id_product'
                )
            )
        );
    }

}