<?php

namespace SE\Shop;

// валютный курс
class EnumValutes extends Base
{
    // получить курс валют
    public function fetch()
    {
        $data = file_get_contents("http://www.cbr.ru/scripts/XML_daily.asp");
        $currencies = simplexml_load_string($data);
        $result[] = array("id" => 'R', "numCode" => 0,
            "charCode" => 'RUB', "name" => 'Российский рубль',
            "value" => 1, "nominal" => 1);
        foreach ($currencies->Valute as $value) {
            $result[] = array("id" => (string) $value["ID"], "numCode" => (int) $value->NumCode,
                "charCode" => (string) $value->CharCode, "name" => (string) $value->Name,
                "value" => (float) $value->Value, "nominal" => (float) $value->Nominal);
        }
        $this->result["items"] = $result;
        $this->result["count"] = count($result);
    }
}
