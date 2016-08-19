<?php

namespace SE\Shop;

use SE\DB as DB;

class Company extends Base
{
    protected $tableName = "company";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'c.*, sug.group_id id_group',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => "se_user_group sug",
                    "condition" => "c.id = sug.company_id"
                )
            )
        );
    }

    public function getContacts($idCompany)
    {
        $u = new DB('company_person', 'cp');
        $u->select('p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) fullName');
        $u->innerJoin('person p', 'p.id = cp.id_person');
        $u->where('cp.id_company = ?', $idCompany);
        $u->orderBy('cp.id');
        return $u->getList();
    }

    public function getOrders($idCompany)
    {
        return (new Order())->fetchByCompany($idCompany);
    }

    protected function getAddInfo()
    {
        $result["contacts"] = $this->getContacts($this->result["id"]);
        $result["orders"] = $this->getOrders($this->result["id"]);
        return $result;
    }

    private function saveContacts()
    {
        if (!isset($this->input["contacts"]))
            return true;

        try {
            $idCompany = $this->input["id"];
            DB::saveManyToMany($idCompany, $this->input["contacts"],
                array("table" => "company_person", "key" => "id_company", "link" => "id_person"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить контакты компании!";
            throw new Exception($this->error);
        }
    }

    protected function saveAddInfo()
    {
        return $this->saveContacts();
    }

}