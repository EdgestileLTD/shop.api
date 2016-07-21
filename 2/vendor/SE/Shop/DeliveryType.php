<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class DeliveryType extends Base
{
    public function fetch()
    {
        $type['id'] = "simple";
        $type['code'] = "simple";
        $type['name'] = "Простая доставка";
        $types[] = $type;

        $type = null;
        $type['id'] = "region";
        $type['code'] = "region";
        $type['name'] = "Доставка по регионам";
        $type['isTreeMode'] = true;         // позволять создовать дочерние доставки
        $type['isNeedRegion'] = true;       // позволять создовать список регионов доставок
        $type['isNeedConditions'] = true;   // позволять создовать список условий доставок
        $types[] = $type;

        $type = null;
        $type['id'] = "subregion";
        $type['code'] = "subregion";
        $type['name'] = "Доставка по регионам с подпунктами";
        $type['isTreeMode'] = true;         // позволять создовать дочерние доставки
        $type['isNeedRegion'] = true;       // позволять создовать список регионов доставок
        $type['isNeedConditions'] = true;   // позволять создовать список условий доставок
        $types[] = $type;


        $type = null;
        $type['id'] = "ems";
        $type['code'] = "ems";
        $type['name'] = "EMS (калькулятор)";
        $types[] = $type;

        $type = null;
        $type['id'] = "post";
        $type['code'] = "post";
        $type['name'] = "Почта России";
        $types[] = $type;

        $this->result['count'] = sizeof($types);
        $this->result['items'] = $types;

        return $types;
    }

    public function sort()
    {
        try {
            $sortIndexes = $this->input["sortIndexes"];

            $u = new DB('shop_deliverytype', 'sdt');
            foreach ($sortIndexes as $index) {
                $u->select('id, sort');
                if ($u->find($index->id)) {
                    $u->sort = $index["index"];
                    $u->save();
                }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся созранить позиции доставки!";
        }

    }
}