<?php

namespace SE\Shop;

use SE\DB as DB;

// оплата
class Payment extends Base
{
    protected $tableName = "shop_order_payee";

    public function save()
    {
        /** сохранить (сопоставить данные страницы с данными БД)
         *
         * delete в shop/base
         * getAddInfo в Order получить информацию
         *
         * 1 получаем массив id из БД
         * 2 цикл по id
         *   2.1 если есть id - в массив
         *   2.2 если нет id  - на добавление
         * 3 сравниваем массивы - в JS отсутствует, на удаление
         *
         * @param array $this->input["payments"] массив всех платежей с JS страницы
         */

        $idBase = $this->getPayments($this->input["idOrder"]); // 1
        $idsPage = array();
        $deleteIDs = array();
        foreach ($this->input["payments"] as $item) {
            if ($item["id"]) {
                $idsPage[$item["id"]] = true; // 2.1
            } else {
                $this->savePayment($item); // 2.2
            }
        } // 2
        foreach ($idBase as $id) {
            if(!$idsPage[$id]) array_push($deleteIDs, $id);
        } // 3
        $this->deletePayments($deleteIDs);
    }

    protected function getPayments($idOrder) {
        /** получить id-шники из таблицы shop_order_payee
         * @param int $idOrder id заказа
         * @return array $base массив ids из БД
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB('shop_order_payee', 'sop');
        $u->select('sop.id');
        $u->where('sop.id_order = ?', $idOrder);
        $base = $u->getList();
        $return = array();
        foreach ($base as $array) {
            array_push($return, $array["id"]);
        } // вытаскивае id из массивов
        unset($u);
        return $return;
    }

    protected function savePayment($input) {
        /** сохранить платеж
         * @param array $input данные по записываемой строке shop_order_payee
         * @return array $input
         */
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');

        $input["curr"] = $this->getBaseCurr()["name"];

        try {
            $this->correctValuesBeforeSave();
            $this->correctAll();
            DB::beginTransaction();
            $u = new DB("shop_order_payee");
            $u->setValuesFields($input);
            $input["id"] = $u->save();
            if ($input["id"] && $this->saveAddInfo()) {
                $this->info();
                DB::commit();
                $this->afterSave();
            } else throw new Exception();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся сохранить информацию об объекте!" : $this->error;
        }

        if (!empty($input["idOrder"]))
            Order::checkStatusOrder($input["idOrder"], $input["paymentType"]);

        return $input;

    }

    protected function deletePayments($ids) {
        /** удалить массив строк по id из shop_order_payee
         * @param aray $ids массив id-шников на удаление
         */
        if (count($ids) > 0) {
            $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
            $this->correctAll('del');
            $u = new DB("shop_order_payee", "sop");
            if (!empty("shop_order_payee")) {
                $u->where('id IN (?)', join(',', $ids))->deleteList();
            }
            unset($u);
        }
    }

    protected function getBaseCurr()
    {
        /**
         * ПОЛУЧИТЬ БАЗОВУЮ ВАЛЮТУ сайта
         * получаем название, приставку, окончание, имя
         */

        $u = new DB('main', 'm');
        $u->select('mt.name, mt.title, mt.name_front, mt.name_flang');
        $u->innerJoin('money_title mt', 'm.basecurr = mt.name');;
        $base = $u->fetchOne();
        unset($u);
        return $base;
    }

    protected function getSettingsFetch()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
            ),
            "convertingValues" => array(
                "amount",
                "totalAmount"
            )
        );
    } // получить настройки

    protected function getSettingsInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
    } // получить информацию по настройкам

    private function getNewNum()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $u = new DB("shop_order_payee");
        $u->select("MAX(num) num");
        $u->where("sop.year = YEAR(NOW())");
        $result = $u->fetchOne();
        return $result["num"] + 1;
    } // получить новый номер

    protected function correctValuesBeforeSave()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        if (empty($this->input["id"])) {
            $this->input["num"] = $this->getNewNum();
            $this->input["year"] = date("Y");
        }
        $this->saveOrderAccount();
    } // правильные заначения перед сохранением

    protected function correctItemsBeforeFetch($items = array())
    {
        /*
         * ДАННЫЕ ПО ВАЛЮТАМ
         * запрашиваем название,приставки/окончания валют
         * и добавляем в эллементы массива соответственно
         */
        $u = new DB('money_title', 'mt');
        $u->select('mt.name name, mt.title title, mt.name_front nameFront, mt.name_flang nameFlang');
        $currList = $u->getList();
        unset($u);

        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        foreach ($items as &$item) {
            $item["name"] = empty($item["name"]) ? "С лицевого счёта" : $item["name"];
            $item["amount"] = number_format($item["amount"], 2, '.', ' ');

            foreach ($currList as $currUnit)
                if ($item["curr"] == $currUnit["name"]) {
                    $item["titleCurr"] = $currUnit["title"];
                    $item["nameFront"] = $currUnit["nameFront"];
                    $item["nameFlang"] = $currUnit["nameFlang"];
                };
        };
        return $items;
    } // правильные значения перед извлечением

    private function saveOrderAccount()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
            $data["curr"] = $this->input["curr"];
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
            $data["curr"] = $this->input["curr"];
            $data["outPayee"] = $this->input["amount"];
            $document = 'Оплата заказа № ' . $this->input["idOrder"];
            $data["docum"] = $document;
            $u->setValuesFields($data);
            $this->input["idUserAccountOut"] = $u->save();
        } else $this->input["idUserAccountOut"] = 0;
    } // сохранить учетную запись заказа

    protected function getAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
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
    } // добавить полученную информацию

    public function fetchByOrder($idOrder)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $this->setFilters(array("field" => "idOrder", "value" => $idOrder));
        return $this->fetch();
    } // выбор по заказу

}
