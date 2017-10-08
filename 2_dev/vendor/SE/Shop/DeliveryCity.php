<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// города доставки
class DeliveryCity extends Base
{
    // получить
    public function fetch()
    {
        $this->result['items'] = array();
        $citys = json_decode(file_get_contents(dirname(__FILE__) . '/delivery/sdek/rus.json'), true);
        //asort($citys);
        foreach($citys as $city=>$id) {
            $this->result['items'][] = array('id'=>$id, 'city'=>$city);
        }
        $this->result['count'] = count($this->result['items']);
    }

}