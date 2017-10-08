<?php

namespace SE\Shop;

// тип особенности
class FeatureType extends Base
{
    // получать
    public function fetch()
    {
        $isList = $this->input["isList"];

        $items[0]["id"] = "0";
        $items[0]["name"] = "Список";
        $items[0]["code"] = "list";
        $items[0]["valueType"] = "L";
        $items[1]["id"] = "1";
        $items[1]["name"] = "Цвет";
        $items[1]["code"] = "colorlist";
        $items[1]["valueType"] = "CL";
        if(!$isList) {
            $items[2]["id"] = "2";
            $items[2]["name"] = "Число";
            $items[2]["code"] = "number";
            $items[2]["valueType"] = "D";
            $items[3]["id"] = "3";
            $items[3]["name"] = "Логический";
            $items[3]["code"] = "bool";
            $items[3]["valueType"] = "B";
            $items[4]["id"] = "4";
            $items[4]["name"] = "Строка";
            $items[4]["code"] = "string";
            $items[4]["valueType"] = "S";
        }

        $this->result["items"] = $items;
        $this->result["count"] = count($items);

        return $items;
    }
}
