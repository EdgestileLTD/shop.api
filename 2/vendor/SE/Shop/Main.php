<?php

namespace SE\Shop;

class Main extends Base
{
    protected $tableName = "main";

    protected function getAddInfo()
    {
        $currency = new Currency();
        return array("listCurrency" => $currency->fetch());
    }
}