<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// отладка
function debugging($group,$funct,$act) {    // группа логов / функция / комент
    // значение:  True - печатать в логи /  False - не печатать

    $print = array(
        'funct'                     => False,   // безымянные
    );

    if($print[$group] == True) {
        $wrLog          = __FILE__;
        $Indentation    = str_repeat(" ", (100 - strlen($wrLog)));
        $wrLog          = "{$wrLog} {$Indentation}| Start function: {$funct}";
        $Indentation    = str_repeat(" ", (150 - strlen($wrLog)));
        writeLog("{$wrLog}{$Indentation} | Act: {$act}");
    }
}

class Discount extends Base
{
    protected $tableName = "shop_discounts";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    // получить натройки
    protected function getSettingsFetch()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return array(
            "select" => 'sd.*',
            "left" => array(
                "type" => "inner",
                "table" => 'shop_discount_links sdl',
                "condition" => 'sdl.discount_id = sd.id'
            )
        );
    }

    // добавить информацию
    protected function getAddInfo()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        $result["listGroupsProducts"] = $this->getListGroupsProducts($this->result["id"]);
        $result["listProducts"] = $this->getListProducts($this->result["id"]);
        $result['listContacts'] = $this->getListContacts($this->result["id"]);
        $result['listGroupsContacts'] = $this->getListGroupsContacts($this->result["id"]);
        return $result;
    }

    // сохранить информацию
    protected function saveAddInfo()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        return $this->saveProducts() && $this->saveGroupsProducts() && $this->saveContacts() && $this->saveGroupsContacts() ;
    }

    // получить список продуктов
    private function getListProducts($id) {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr');
            $u->innerJoin("shop_price sp", "sdl.id_price = sp.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sp.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список товаров скидки!";
        }
    }

    // получить список групп продуктов
    private function getListGroupsProducts($id) {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('sg.id, sg.code_gr, sg.name');
            $u->innerJoin("shop_group sg", "sdl.id_group = sg.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sg.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп товаров скидки!";
        }
    }

    // получить список контактов
    private function getListContacts($id) {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('p.id, p.first_name, p.sec_name, p.last_name, p.email');
            $u->innerJoin("person p", "sdl.id_user = p.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("p.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список контактов скидки!";
        }
    }

    // получить лист групп контактов
    private function getListGroupsContacts($id) {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        try {
            $u = new DB('shop_discount_links', 'sdl');
            $u->select('sg.id, sg.name, sg.title');
            $u->innerJoin("se_group sg", "sdl.id_usergroup = sg.id");
            $u->where("sdl.discount_id = $id");
            $u->groupBy("sg.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп контактов скидки!";
        }
    }


    // сохранить продукты
    private function saveProducts()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__,'сохраняемые в базу значения товара'); // отладка
        if (!isset($this->input["listProducts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id) {
                //writeLog($this->input["listProducts"]); // сохраняемые в базу значения товара
                DB::saveManyToMany($id, $this->input["listProducts"],
                    array("table" => "shop_discount_links", "key" => "discount_id", "link" => "id_price"));
            }
            // перевод переключателя скидки (в товаре) в вкл
            foreach ($this->input["listProducts"] as $prod) {
                $data = array('id'=> $prod['id'], 'discount'=>'Y');
                $u = new DB('shop_price');
                $u->setValuesFields($data);
                $u->save();
            }


            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить товары для скидки!";
            throw new Exception($this->error);
        }

    }

    // сохранить группы продуктов
    private function saveGroupsProducts()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        if (!isset($this->input["listGroupsProducts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["listGroupsProducts"],
                    array("table" => "shop_discount_links", "key" => "discount_id", "link" => "id_group"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группы для скидки!";
            throw new Exception($this->error);
        }
    }

    // сохранить контакты
    private function saveContacts()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        if (!isset($this->input["listContacts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["listContacts"],
                    array("table" => "shop_discount_links", "key" => "discount_id", "link" => "id_user"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить контакт для скидки!";
            throw new Exception($this->error);
        }
    }

    // сохранить группы контактов
    private function saveGroupsContacts()
    {
        debugging('funct',__FUNCTION__.' '.__LINE__); // отладка
        if (!isset($this->input["listGroupsContacts"]))
            return true;

        try {
            foreach ($this->input["ids"] as $id)
                DB::saveManyToMany($id, $this->input["listGroupsContacts"],
                    array("table" => "shop_discount_links", "key" => "discount_id", "link" => "id_usergroup"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить группы контакт для скидки!";
            throw new Exception($this->error);
        }
    }



}
