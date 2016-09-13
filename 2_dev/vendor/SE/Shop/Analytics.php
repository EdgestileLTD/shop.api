<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Analytics extends Base
{

    private $startDate;
    private $endDate;

    function __construct($input = null)
    {
        parent::__construct($input);
        $this->startDate = !empty($input["startDate"]) ? strtotime($input["startDate"]) : null;
        $this->endDate = !empty($input["endDate"]) ? strtotime($input["endDate"]) : null;
    }

    public function info()
    {
        $this->result["countVisitors"] = $this->countVisitors();
        $this->result["countPaidOrders"] = $this->countOrders(true);
        $this->result["countAllOrders"] = $this->countOrders();
        $this->result["countPaidCustomers"] = $this->countCustomers(true);
        $this->result["countAllCustomers"] = $this->countCustomers();
        $this->result["sumPaidOrders"] = $this->sumPaidOrders();
        $this->result["sumPurchase"] = $this->sumPurchase();

        return $this->result;
    }

    private function countVisitors()
    {
        try {
            $u = new DB('shop_stat_session');
            $u->where('TRUE');
            if ($this->startDate)
                $u->andWhere('created_at >= ?', $this->startDate);
            if ($this->endDate)
                $u->andWhere('created_at <= ?', $this->endDate);
            return (int) $u->getListCount();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить количество посетителей!";
        }
    }

    private function sumPaidOrders()
    {
        try {
            $u = new DB('shop_order');
            $u->select("(SUM((sto.price - IFNULL(sto.discount, 0)) * sto.count) - IFNULL(so.discount, 0)) sum");
            $u->innerJoin("shop_tovarorder sto", 'sto.id_order = so.id');
            $u->where('so.is_delete = "N"');
            $u->andWhere('so.status = "Y"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= ?', $this->startDate);
            if ($this->endDate)
                $u->andWhere('so.created_at <= ?', $this->endDate);
            return (float) $u->fetchOne()['sum'];
        } catch (Exception $e) {
            $this->error = "Не удаётся получить сумму оплаченных заказов!";
        }
    }

    private function countOrders($isPaid = false)
    {
        try {
            $u = new DB('shop_order');
            $u->where('is_delete = "N"');
            if ($isPaid)
                $u->andWhere('status = "Y"');
            if ($this->startDate)
                $u->andWhere('created_at >= ?', $this->startDate);
            if ($this->endDate)
                $u->andWhere('created_at <= ?', $this->endDate);
            return (int) $u->getListCount();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить количество заказов!";
        }
    }

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
                $u->andWhere('so.created_at >= ?', $this->startDate);
            if ($this->endDate)
                $u->andWhere('so.created_at <= ?', $this->endDate);
            $u->groupBy('p.id');
            $countPersons = $u->getListCount();

            $u = new DB('company', 'c');
            $u->select('c.id');
            $u->innerJoin('shop_order so', 'so.id_company = c.id');
            $u->where('TRUE');
            if ($isPaid)
                $u->andWhere('so.status = "Y" AND is_delete = "N"');
            if ($this->startDate)
                $u->andWhere('so.created_at >= ?', $this->startDate);
            if ($this->endDate)
                $u->andWhere('so.created_at <= ?', $this->endDate);
            $u->groupBy('c.id');
            $countCompanies = $u->getListCount();

            return $countPersons + $countCompanies;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить количество клиентов!";
        }
    }

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
                $u->andWhere('so.created_at >= ?', $this->startDate);
            if ($this->endDate)
                $u->andWhere('so.created_at <= ?', $this->endDate);
            return (float) $u->fetchOne()['sum'];
        } catch (Exception $e) {
            $this->error = "Не удаётся получить сумму закупок!";
        }
    }


}
