<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// группа пользовательских полей
class CustomFieldGroup extends Base
{
    protected $tableName = "shop_userfield_groups";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    // сохранить
    public function save()
    {
        $u = new DB('shop_userfield_groups');
        $field = $u->getField('data');
        if ($field['Type'] !== "enum('contact','order','company','productgroup','product','public')") {
            DB::query("ALTER TABLE `shop_userfield_groups` CHANGE `data` `data` ENUM('contact','order','company','productgroup','product','public') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;");
        }
        parent::save();
    }
    
}
