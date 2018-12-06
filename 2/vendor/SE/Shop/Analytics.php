<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

// аналитика
class Analytics extends Base
{

    private $startDate;
    private $endDate;
    private $data;

    // сборка
    function __construct($input = null)
    {
        parent::__construct($input);
        $this->startDate = !empty($this->input["startDate"]) ? strtotime($this->input["startDate"]) : null;
        $this->endDate = !empty($this->input["endDate"]) ? strtotime($this->input["endDate"]) + 86400 : null;
        $this->data = $this->input["data"];
    }

    // информация
    public function info($id = NULL)
    {
        if (empty($this->data)) {
            $this->result["countVisitors"] = $this->countVisitors();
            $this->result["countPaidOrders"] = $this->countOrders(true);
            $this->result["countAllOrders"] = $this->countOrders();
            $this->result["countPaidCustomers"] = $this->countCustomers(true);
            $this->result["countAllCustomers"] = $this->countCustomers();
            $this->result["sumPaidOrders"] = $this->sumPaidOrders();
            $this->result["sumPurchase"] = $this->sumPurchase();
        } elseif ($this->data == "funnel")
            $this->result["funnel"] = $this->getFunnel();
        elseif ($this->data == "statisticsOrders")
            $this->result["statisticsOrders"] = $this->getStatisticsOrders();
        elseif ($this->data == "products")
            $this->result["products"] = $this->getProducts();

        return $this->result;
    }

    // количество посетителей
    private function countVisitors()
    {
        try {
            $u = new DB('shop_stat_events');
            $u->select("COUNT(DISTINCT sse.id_session, sse.number) `count`");
            $u->where('TRUE');
            if ($this->startDate)
                $u->andWhere('created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = $u->getList();
            return $result[0]["count"];
        } catch (Exception $e) {
            $this->error = "Не удаётся получить количество посетителей!";
        }
    }

    // сумма оплаченных заказов
    private function sumPaidOrders()
    {
        try {
            $u = new DB('shop_order');
            $u->select("(SUM((sto.price - IFNULL(sto.discount, 0)) * sto.count) - IFNULL(so.discount, 0)) sum");
            $u->innerJoin("shop_tovarorder sto", 'sto.id_order = so.id');
            $u->where('so.is_delete = "N"');
            $u->andWhere('so.status = "Y"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('so.created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = (float) $u->fetchOne();
            return $result['sum'];
        } catch (Exception $e) {
            $this->error = "Не удаётся получить сумму оплаченных заказов!";
        }
    }

    // подсчеты
    private function countOrders($isPaid = false)
    {
        try {
            $u = new DB('shop_order');
            $u->where('is_delete = "N"');
            if ($isPaid)
                $u->andWhere('status = "Y"');
            if ($this->startDate)
                $u->andWhere('created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('created_at <= "?"', date("Y-m-d", $this->endDate));
            return (int) $u->getListCount();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить количество заказов!";
        }
    }

    // количество клиентов
    private function countCustomers($isPaid = false)
    {
        try {
            $u = new DB('person', 'p');
            $u->select('p.id');
            $u->innerJoin('shop_order so', 'so.id_author = p.id');
            $u->where('TRUE');
            if ($isPaid)
                $u->andWhere('so.status = "Y" AND so.id_company IS NULL AND is_delete = "N"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('so.created_at <= "?"', date("Y-m-d", $this->endDate));
            $u->groupBy('p.id');
            $countPersons = $u->getListCount();

            $u = new DB('company', 'c');
            $u->select('c.id');
            $u->innerJoin('shop_order so', 'so.id_company = c.id');
            $u->where('TRUE');
            if ($isPaid)
                $u->andWhere('so.status = "Y" AND is_delete = "N"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('so.created_at <= "?"', date("Y-m-d", $this->endDate));
            $u->groupBy('c.id');
            $countCompanies = $u->getListCount();

            return $countPersons + $countCompanies;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить количество клиентов!";
        }
    }

    // сумма покупки
    private function sumPurchase()
    {
        try {
            $u = new DB('shop_order');
            $u->select("SUM(sp.price_purchase * sto.count) sum");
            $u->innerJoin("shop_tovarorder sto", 'sto.id_order = so.id');
            $u->innerJoin("shop_price sp", 'sto.id_price = sp.id');
            $u->where('so.is_delete = "N"');
            $u->andWhere('so.status = "Y"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('so.created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = (float) $u->fetchOne();
            return $result['sum'];
        } catch (Exception $e) {
            $this->error = "Не удаётся получить сумму закупок!";
        }
    }

    // получить воронку
    private function getFunnel()
    {
        $rows = array();

        $funnel["countVisitors"] = $this->countVisitors();

        try {
            $u = new DB("shop_stat_events", 'sse');
            $u->select("COUNT(DISTINCT sse.id_session, sse.number) `count`");
            $u->where('event = "add cart"');
            if ($this->startDate)
                $u->andWhere('created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = $u->getList();
            $funnel["countAddCart"] = $result[0]["count"];

            $u = new DB("shop_stat_events", 'sse');
            $u->select("COUNT(DISTINCT sse.id_session, sse.number) `count`");
            $u->where('(event = "show product" OR event = "add cart")');
            if ($this->startDate)
                $u->andWhere('created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = $u->getList();
            $funnel["countViewProduct"] = $result[0]["count"];

            $u = new DB("shop_stat_events", 'sse');
            $u->select("COUNT(DISTINCT sse.id_session, sse.number) `count`");
            $u->innerJoin("(SELECT * FROM `shop_stat_events` sse WHERE sse.event = 'add cart') sse1",
                'sse.id_session = sse1.id_session AND sse.number = sse1.number');
            $u->where("sse.event = 'view shopcart'");
            if ($this->startDate)
                $u->andWhere('sse.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('sse.created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = $u->getList();
            $funnel["countViewCart"] = $result[0]["count"];

            $u = new DB("shop_stat_events", 'sse');
            $u->select("COUNT(DISTINCT sse.id_session) `count`");
            $u->where('event = "place order"');
            if ($this->startDate)
                $u->andWhere('sse.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('sse.created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = $u->getList();
            $funnel["countPlaceOrder"] = $result[0]["count"];

            $u = new DB("shop_stat_events", 'sse');
            $u->select("COUNT(DISTINCT sse.id_session) `count`");
            $u->where('event = "confirm order"');
            if ($this->startDate)
                $u->andWhere('sse.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('sse.created_at <= "?"', date("Y-m-d", $this->endDate));
            $result = $u->getList();
            $funnel["countConfirmOrder"] = $result[0]["count"];

            $u = new DB("shop_stat_events", 'sse');
            $u->select("COUNT(DISTINCT sse.content) `count`");
            $u->innerJoin("shop_order so", "sse.content = so.id");
            $u->where('so.is_delete = "N" AND so.status = "Y"');
            if ($this->startDate)
                $u->andWhere('sse.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('sse.created_at <= "?"', date("Y-m-d", $this->endDate));
            $u->groupBy("so.id_author");
            $result = $u->getList();
            $funnel["countPaidOrder"] = $result[0]["count"];

            $rows[] = array("Name" => "countVisitors",
                "Title" => "Посетили сайт", "Value" => $funnel["countVisitors"], "Color" => "#FF6384");
            $rows[] = array("Name" => "countViewProduct",
                "Title" => "Посмотрели товар", "Value" => $funnel["countViewProduct"], "Color" => "#9400D3");
            $rows[] = array("Name" => "countAddCart",
                "Title" => "Положили в корзину", "Value" => $funnel["countAddCart"], "Color" => "#FFCE56");
            $rows[] = array("Name" => "countViewCart",
                "Title" => "Перешли в корзину", "Value" => $funnel["countViewCart"], "Color" => "#36A2EB");
            $rows[] = array("Name" => "countPlaceOrder",
                "Title" => "Оформили заказ", "Value" => $funnel["countPlaceOrder"], "Color" => "#4BC0C0");
            $rows[] = array("Name" => "countConfirmOrder",
                "Title" => "Подтвердили заказ", "Value" => $funnel["countConfirmOrder"], "Color" => "#CEFF56");
            $rows[] = array("Name" => "countPaidOrder",
                "Title" => "Оплатили заказ", "Value" => $funnel["countPaidOrder"], "Color" => "#228B22");
            return $rows;
        } catch (Exception $e) {
            $this->error = "Не удаётся построить воронку продаж!";
        }
        return null;
    }

    // получить заказы на получение статистики
    private function getStatisticsOrders()
    {
        try {
            $u = new DB('shop_order');
            $u->select("so.date_order, (SUM((sto.price - IFNULL(sto.discount, 0)) * sto.count) - IFNULL(so.discount, 0)) sum");
            $u->innerJoin("shop_tovarorder sto", 'sto.id_order = so.id');
            $u->where('so.is_delete = "N"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('so.created_at <= "?"', date("Y-m-d", $this->endDate));
            $u->groupBy('so.date_order');
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить статистику по заказам!";
        }
        return null;
    }

    // получить продукты
    private function getProducts()
    {
        try {
            $u = new DB('shop_tovarorder', 'st');
            $u->select("st.nameitem name, SUM(st.count) count, 
                    IFNULL((1 - (IFNULL(sp.price_purchase, sp.price)) / sp.price) * 100, 100) profitability,
                    IFNULL(SUM(sp.price - sp.price_purchase), AVG(st.price)) profit");
            $u->innerJoin('shop_order so', "so.id = st.id_order");
            $u->leftJoin('shop_price sp', 'sp.id = st.id_price');
            $u->where("so.status = 'Y' AND so.inpayee = 'N'");
            if ($this->startDate)
                $u->andWhere('so.created_at >= "?"', date("Y-m-d", $this->startDate));
            if ($this->endDate)
                $u->andWhere('so.created_at <= "?"', date("Y-m-d", $this->endDate));
            $u->groupBy("sp.id");
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить аналитику по товарам!";
        }
    }
}
