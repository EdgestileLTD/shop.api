<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Filter extends Base
{
    public function fetch()
    {
        try {
            $u = new DB('shop_feature', 'sf');
            $u->select('id, name, sort');
            $u->orderBy('sf.sort, sf.name');

            $item['code'] = 'price';
            $item['name'] = 'Цена';
            $items[] = $item;
            $item['code'] = 'brand';
            $item['name'] = 'Бренды';
            $items[] = $item;
            $item['code'] = 'flag_hit';
            $item['name'] = 'Хиты';
            $items[] = $item;
            $item['code'] = 'flag_new';
            $item['name'] = 'Новинки';
            $items[] = $item;

            $objects = $u->getList();
            foreach ($objects as $item) {
                $filter = null;
                $filter['id'] = $item['id'];
                $filter['name'] = $item['name'];
                $items[] = $filter;
            }

            $this->result['count'] = sizeof($objects) + 4;
            $this->result['items'] = $items;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список фильтров!";
        }


    }
}