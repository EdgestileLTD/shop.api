<?php

namespace SE\Shop;

class Review extends Base
{
    protected $tableName = "shop_reviews";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sr.*, (sr.active = "Y") is_active,
                 CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) name_user, sp.name name_product',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'person p',
                    "condition" => 'p.id = sr.id_user'
                ),
                array(
                    "type" => "inner",
                    "table" => 'shop_price sp',
                    "condition" => 'sp.id = sr.id_price'
                )
            )
        );
    }

    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    public function fetchByIdProduct($idProduct)
    {
        if (!$idProduct)
            return array();

        $this->setFilters(array("field" => "idPrice", "value" => $idProduct));
        return $this->fetch();
    }

}
