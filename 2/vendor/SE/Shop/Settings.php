<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Settings extends Base
{
    protected $tableName = "shop_settings";

    public function fetch()
    {
        $res = $this->postRequest('lib/atol.php', array());
        try {
            $db = new DB('shop_settings','ss');
            $db->select("ss.*");
            $settings = $db->getList();

            $db = new DB('shop_setting_groups','ssg');
            $db->select("ssg.*");
            $this->result['groups'] = $db->getList();

            $db = new DB('shop_setting_values','ssv');
            $db->select("ssv.*");
            $values = $db->getList();


            $this->result['settings'] = array();
            foreach($settings as $setting){
                foreach ($values as $value) {
                    if($value['idSetting'] == $setting['id']){
                        $setting['value'] = $value['value'];
                        $setting['valueID'] = $value['id'];
                    }
                }
                if(!isset($setting['value'])){
                    $setting['value'] = $setting['default'];
                }
                if(!empty($setting['listValues'])){
                    // value1|name1,value2|name2,value3|name3
                    $list = explode(',',$setting['listValues']);
                    $setting['listValues'] = array();
                    foreach ($list as $string){
                        // value1|name1
                        $array = explode('|',$string);
                        $setting['listValues'][$array[0]] = $array[1];
                    }
                }
                $this->result['settings'][] = $setting;
            }
            return $this->result;

        } catch (Exception $e) {
            return $this->error = 'Не удалось получить настройки';
        }

    }
    public function save($isTransactionMode = true)
    {
        if(isset($this->input['settings'])){
            foreach($this->input['settings'] as $setting){
                try{
                    DB::beginTransaction();

                    if($setting['type'] == 'bool'){
                        $value = (int) $setting['value'];
                    } else {
                        $value = htmlspecialchars(trim($setting['value']));
                    }
                    @$enabled = $setting['enabled'] ? (int) $setting['enabled'] : 0;
                    $id = $setting['id'];

                    DB::query("UPDATE IGNORE `shop_settings` SET `enabled` = '$enabled' WHERE `shop_settings`.`id` = ".$id);

                    if(isset($setting['valueID']))
                        DB::query("UPDATE IGNORE `shop_setting_values` SET `value` = '$value' WHERE `shop_setting_values`.`id` = ".$setting['valueID']);
                    else
                        DB::query("INSERT IGNORE INTO `shop_setting_values` (`id_setting`, `value`) VALUES ($id, '$value');");

                    DB::commit();
                } catch (Exception $e){
                    DB::rollBack();
                }
            }
        }
        return $this->fetch();
    }
}