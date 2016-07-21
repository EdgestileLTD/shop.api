<?php

namespace SE\Shop;

class Payment extends Base
{
    protected $tableName = "shop_order_payee";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sop.*, (SELECT name_payment FROM shop_payment WHERE id = sop.payment_type) name,
                CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) payer',
            "joins" => array(
                "type" => "inner",
                "table" => 'person p',
                "condition" => 'p.id = sop.id_author'
            ),
            "aggregation" => array(
                "type" => "SUM",
                "field" => "amount",
                "name" => "totalAmount"
            )
        );
    }

    protected function getSettingsInfo()
    {
        return array(
            "select" => 'sop.*, (SELECT name_payment FROM shop_payment WHERE id = sop.payment_type) name,
                CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) payer',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => 'person p',
                    "condition" => 'p.id = sop.id_author'
                ),
                array(
                    "type" => "left",
                    "table" => 'se_user_account sua',
                    "condition" => 'sua.id = sop.id_user_account_out'
                )
            )
        );
    }

    protected function getAddInfo()
    {
        $result = array();
        if ($idAuthor = $this->result["idAuthor"]) {
            $contact = new Contact();
            $result["contact"] = $contact->info($idAuthor);
        }
        if ($idOrder = $this->result["idOrder"]) {
            $order = new Order();
            $result["order"] = $order->info($idOrder);
        }
        return $result;
    }

    public function fetchByOrder($idOrder)
    {
        $this->setFilters(array("field" => "idOrder", "value" => $idOrder));
        return $this->fetch();
    }

}
