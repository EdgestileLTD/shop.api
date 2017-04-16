<?php

namespace SE\Shop;

use SE\DB as DB;

class Payment extends Base
{
    protected $tableName = "shop_order_payee";

    public function save()
    {
        $result = parent::save();
        if (!empty($this->input["idOrder"]))
            Order::checkStatusOrder($this->input["idOrder"], $this->input["paymentType"]);
        return $result;
    }

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sop.*, (SELECT name_payment FROM shop_payment WHERE id = sop.payment_type) name,
                IFNULL(c.name,  CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name)) payer',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'person p',
                    "condition" => 'p.id = sop.id_author'),
                array(
                    "type" => "left",
                    "table" => 'company c',
                    "condition" => 'c.id = sop.id_company'),
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
                IFNULL(c.name,  CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name)) payer',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'person p',
                    "condition" => 'p.id = sop.id_author'
                ),
                array(
                    "type" => "left",
                    "table" => 'se_user_account sua',
                    "condition" => 'sua.id = sop.id_user_account_out'
                ),
                array(
                    "type" => "left",
                    "table" => 'company c',
                    "condition" => 'c.id = sop.id_company'
                )
            )
        );
    }

    private function getNewNum()
    {
        $u = new DB("shop_order_payee");
        $u->select("MAX(num) num");
        $u->where("sop.year = YEAR(NOW())");
        return $u->fetchOne()["num"] + 1;
    }

    protected function correctValuesBeforeSave()
    {
        if (empty($this->input["id"])) {
            $this->input["num"] = $this->getNewNum();
            $this->input["year"] = date("Y");
        }
        $this->saveOrderAccount();
    }

    protected function correctValuesBeforeFetch($items = [])
    {
        foreach ($items as &$item)
            $item["name"] = empty($item["name"]) ? "С лицевого счёта" : $item["name"];
        return $items;
    }

    private function saveOrderAccount()
    {
        $orderId = $this->input["idOrder"];
        if ($this->input["idUserAccountOut"]) {
            $u = new DB('se_user_account', 'sua');
            $u->where('id = ?', $this->input["idUserAccountOut"])->deleteList();
        }
        if ($this->input["idUserAccountIn"] > 0) {
            $u = new DB('se_user_account', 'sua');
            $u->where('id = ?', $this->input["idUserAccountIn"])->deleteList();
        }
        if ($this->input["paymentTarget"] == 1 || $this->input["paymentType"] > 0) {
            $u = new DB('se_user_account', 'sua');
            $data["userId"] = $this->input["idAuthor"];
            $data["companyId"] = $this->input["idCompany"];
            $data["datePayee"] = date("Y-m-d");
            $data["orderId"] = $orderId;
            $data["operation"] = 1;
            $data["inPayee"] = $this->input["amount"];
            $document = null;
            if ($this->input["paymentTarget"] == 1)
                $document = 'Поступление средств на счёт';
            else $document = 'Поступление наличных в счёт заказа № ' . $this->input["idOrder"];
            $data["docum"] = $document;
            $u->setValuesFields($data);
            $this->input["idUserAccountIn"] = $u->save();
        } else $this->input["idUserAccountIn"] = null;

        if ($this->input["paymentTarget"] == 0) {
            $u = new DB('se_user_account', 'sua');
            $data["userId"] = $this->input["idAuthor"];
            $data["companyId"] = $this->input["idCompany"];
            $data["datePayee"] = date("Y-m-d");
            $data["orderId"] = $orderId;
            $data["operation"] = 2;
            $data["inPayee"] = 0;
            $data["outPayee"] = $this->input["amount"];
            $document = 'Оплата заказа № ' . $this->input["idOrder"];
            $data["docum"] = $document;
            $u->setValuesFields($data);
            $this->input["idUserAccountOut"] = $u->save();
        } else $this->input["idUserAccountOut"] = 0;
    }

    protected function getAddInfo()
    {
        $result = [];
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
