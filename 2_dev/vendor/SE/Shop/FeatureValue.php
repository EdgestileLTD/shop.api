<?php

namespace SE\Shop;

class FeatureValue extends Base
{
    protected $tableName = "shop_feature_value_list";
    protected $sortBy = "sort";
    protected $sortOrder = "asc";
    
    public function fetchByIdFeature($idFeature)
    {
        if (!$idFeature)
            return array();

        $this->setFilters(array("field" => "idFeature", "value" => $idFeature));
        return $this->fetch();
    }

}