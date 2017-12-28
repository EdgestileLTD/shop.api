<?php

namespace SE\Shop;

class PreOrder extends Base
{
    protected $tableName = "shop_preorder";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sp.*, pr.name product, 
                DATE_FORMAT(sp.created_at, "%d.%m.%Y") `date`',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'shop_price pr',
                    "condition" => 'pr.id = sp.id_price'
                )
            )
        );
    }

    protected function correctItemsBeforeFetch($items = [])
    {
        foreach ($items as &$item) {
            if (!empty($item['phone']))
                $item['phone'] = Contact::correctPhone($item['phone']);
        }

        return $items;
    }
}
