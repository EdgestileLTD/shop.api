<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// фильтр
class Filter extends Base
{
    // получить список фильтров
    public function fetch()
    {
        try {
            $u = new DB('shop_feature', 'sf');
            $u->select('sf.id, sf.name');
            $u->orderBy('sf.sort, sf.name');

            $default[] = array(
                'code' => 'price',
                'name' => 'Цена',
                'id' => 'price',
            );
            $default[] = array(
                'code' => 'brand',
                'name' => 'Бренды',
                'id' => 'brand',
            );
            $default[] = array(
                'code' => 'flag_hit',
                'name' => 'Хиты',
                'id' => 'flag_hit',
            );
            $default[] = array(
                'code' => 'flag_new',
                'name' => 'Новинки',
                'id' => 'flag_new',
            );

            $items = array();

            if ($this->search) {
                foreach ($default as $key => $val) {
                    if (mb_stripos($val['name'], $this->search) === false) {
                        unset($default[$key]);
                    }
                }
                $u->where('sf.name LIKE "%?%"', $this->search);
            }

            $objects = array_merge($default, $u->getList());

            foreach ($objects as $item) {
                $filter = null;
                $filter['id'] = $item['id'];
                $filter['name'] = $item['name'];
                $filter['code'] = !empty($item['code']) ? $item['code'] : null;
                $items[] = $filter;
            }

            $this->result['count'] = sizeof($items);
            $this->result['items'] = $items;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список фильтров!";
        }
    }
}