<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// тип доставки
class DeliveryType extends Base
{
    // получить
    public function fetch()
    {
        $type['id'] = "simple";
        $type['code'] = "simple";
        $type['name'] = "Простая доставка";
        $type['isIn'] = true;
        $types[] = $type;

        $type = null;
        $type['id'] = "region";
        $type['code'] = "region";
        $type['name'] = "Доставка по регионам";
        $type['isTreeMode'] = true;         // позволять создовать дочерние доставки
        $type['isNeedRegion'] = true;       // позволять создовать список регионов доставок
        $type['isNeedConditions'] = true;   // позволять создовать список условий доставок
        $type['isIn'] = true;
        $types[] = $type;

        $type = null;
        $type['id'] = "subregion";
        $type['code'] = "subregion";
        $type['name'] = "Доставка по регионам с подпунктами";
        $type['isTreeMode'] = true;         // позволять создовать дочерние доставки
        $type['isNeedRegion'] = true;       // позволять создовать список регионов доставок
        $type['isNeedConditions'] = true;   // позволять создовать список условий доставок
        $type['isIn'] = true;
        $types[] = $type;

        $res = $this->postRequest('lib/delivery.php', array('get_services'=>1, 'token' => md5(DB::$dbSerial.DB::$dbPassword)));
        if ($res) {
            $res = json_decode($res, true);
            foreach ($res as $item) {
                $item['isIn'] = false;
                $types[] = $item;
            }
        }
        /*
        $type = null;
        $type['id'] = "ems";
        $type['code'] = "ems";
        $type['name'] = "EMS (калькулятор)";
        $type['isIn'] = false;
        $types[] = $type;

        $type = null;
        $type['id'] = "post";
        $type['code'] = "post";
        $type['name'] = "Почта России";
        $type['isIn'] = false;
        $types[] = $type;

        $type = null;
        $type['id'] = "sdek";
        $type['code'] = "sdek";
        $type['name'] = "СДЭК";
        $type['isIn'] = false;
        $types[] = $type;
        */

        $this->result['count'] = sizeof($types);
        $this->result['items'] = $types;


        return $types;
    }
    // информация
    public function info($id = NULL)
    {
        switch($this->input['type']){
            case 'city':
                if ($res = $this->postRequest('lib/delivery.php', array(
                    'get_cities' => $this->input['value'],
                    'limit' => 10,
                    'token' => md5(DB::$dbSerial.DB::$dbPassword)
                ))) {
                    return $this->result = json_decode($res, true);
                }
                break;
            case 'settings':
                if ($res = $this->postRequest('lib/delivery.php', array(
                    'get_settings' => 1,
                    'id_delivery' => $this->input['id_delivery'],
                    'code' => $this->input['code'],
                    'token' => md5(DB::$dbSerial.DB::$dbPassword)
                ))) {
                    return $this->result = json_decode($res, true);
                }
                break;
            case 'save':
                if(!empty($this->input['fields']) and isset($this->input['fields'])){
                    if ($res = $this->postRequest('lib/delivery.php', array(
                        'save_settings' => 1,
                        'id_delivery' => $this->input['id_delivery'],
                        'settings' => json_encode($this->input['fields'],JSON_PRETTY_PRINT),
                        'token' => md5(DB::$dbSerial.DB::$dbPassword)
                    ))) {
                        return $this->result = $res;
                    }
                }
                return $this->result = true;
                break;
        }
        $this->error = 'Неправильный запрос';
    }

    //сортировка
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