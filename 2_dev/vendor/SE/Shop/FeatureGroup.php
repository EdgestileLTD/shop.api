<?php

namespace SE\Shop;

// группа особенностей
class FeatureGroup extends Base
{
    protected $tableName = "shop_feature_group";    // группа магазинов
    protected $sortBy = "sort";                     // сортировка
    protected $sortOrder = "asc";                   // по возрастанию

}
