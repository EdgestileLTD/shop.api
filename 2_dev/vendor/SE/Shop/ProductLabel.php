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

    protected function correctItemsBeforeFetch($items = [])
    {
        $resultItems = [];

        foreach ($items as $item) {
            $item['imageFile'] = $item['image'];
            if ($item['imageFile']) {
                if (strpos($item['imageFile'], "://") === false) {
                    $item['imageUrl'] = 'http://' . $this->hostname . "/images/rus/labels/" . $item['imageFile'];
                    $item['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/labels/" . $item['imageFile'];
                } else {
                    $item['imageUrl'] = $item['imageFile'];
                    $item['imageUrlPreview'] = $item['imageFile'];
                }
            }
            $resultItems[] = $item;
        }

        return $resultItems;
    }

}