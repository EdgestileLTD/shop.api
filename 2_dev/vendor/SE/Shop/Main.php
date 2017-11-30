<?php

namespace SE\Shop;

// ГЛАВНЫЙ
class Main extends Base
{
    protected $tableName = "main";


    // добавить полученную информацию
    protected function getAddInfo()
    {
        $currency = new Currency(); // валюта
        return array("listCurrency" => $currency->fetch());
    }
}