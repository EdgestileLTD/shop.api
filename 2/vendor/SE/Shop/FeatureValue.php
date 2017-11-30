<?php

namespace SE\Shop;

// стоимость особенности
class FeatureValue extends Base
{
    protected $tableName = "shop_feature_value_list";   // список функций магазина
    protected $sortBy = "sort";                         // сортировка
        protected $sortOrder = "asc";                   // по возрастанию
    protected $limit = 10000;                           // предел

    // выборка по id
    public function fetchByIdFeature($idFeature)
    {
        if (!$idFeature)
            return [];

        $this->setFilters(array("field" => "idFeature", "value" => $idFeature)); // набор фильтров
        return $this->fetch();                                                   // полученное отправить
    }

}