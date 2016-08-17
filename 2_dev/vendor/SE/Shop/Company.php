<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;
use \PHPExcel as PHPExcel;
use \PHPExcel_Writer_Excel2007 as PHPExcel_Writer_Excel2007;
use \PHPExcel_Style_Fill as PHPExcel_Style_Fill;

class Company extends Base
{
    protected $tableName = "company";

    private function getContacts($idCompany)
    {
        $u = new DB('company_person', 'cp');
        $u->select('p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) fullName');
        $u->innerJoin('person p', 'p.id = cp.id_person');
        $u->where('cp.id_company = ?', $idCompany);
        $u->orderBy('cp.id');
        return $u->getList();
    }

    protected function getAddInfo()
    {
        $result["contacts"] = $this->getContacts($this->result["id"]);
        return $result;
    }
}