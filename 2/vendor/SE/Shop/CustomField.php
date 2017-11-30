<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// пользовательское поле
class CustomField extends Base
{
    protected $tableName = "shop_userfields";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    // правильные значения перед сохранением
    public function correctValuesBeforeSave()
    {
        $u = new DB($this->tableName);
        $field = $u->getField('data');
        $u->add_field('def', 'varchar(255)');
        if ($field['Type'] !== "enum('contact','order','company','productgroup','product','public')") {
            DB::query("ALTER TABLE `{$this->tableName}` CHANGE `data` `data` ENUM('contact','order','company','productgroup','product','public') CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;");
        }
        $this->input["idGroup"] = empty($this->input["idGroup"]) ? null : $this->input["idGroup"];
    }
}
