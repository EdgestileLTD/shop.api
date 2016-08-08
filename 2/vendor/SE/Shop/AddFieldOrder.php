<?php

namespace SE\Shop;

class AddFieldOrder extends Base
{
    protected $tableName = "shop_userfields";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";

    public function correctValuesBeforeSave()
    {
        $this->input["idGroup"] = empty($this->input["idGroup"]) ? null : $this->input["idGroup"];
    }
}
