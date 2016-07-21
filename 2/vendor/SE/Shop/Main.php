<?php

namespace SE\Shop;

class Main extends Base
{
    protected $tableName = "main";

    protected function getAddInfo()
    {
        return array("listCurrency" => (new Currency())->fetch());
    }
}