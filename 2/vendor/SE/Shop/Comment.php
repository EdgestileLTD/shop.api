<?php

namespace SE\Shop;

// коментарий
class Comment extends Base
{
    protected $tableName = "shop_comm";

    // получить настройки
    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sc.*, sp.id id_product, sp.name name_product',
            "joins" => array(
                "type" => "inner",
                "table" => 'shop_price sp',
                "condition" => 'sp.id = sc.id_price'
            )
        );
    }

    // получить информацию по настройкам
    protected function getSettingsInfo()
    {
        return $this->getSettingsFetch();
    }

    // выбор продукта по id
    public function fetchByIdProduct($idProduct)
    {
        if (!$idProduct)
            return array();

        $this->setFilters(array("field" => "idPrice", "value" => $idProduct));
        return $this->fetch();
    }
}
