<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// компания
class Company extends Base
{
    protected $tableName = "company";

    // получить настройки
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

    protected function correctItemsBeforeFetch($items = [])
    {
        foreach ($items as &$item)
            $item['phone'] = Contact::correctPhone($item['phone']);

        return $items;
    }

    // получить информацию по настройкам
    protected function getSettingsInfo()
    {
        return array(
            "select" => 'c.*, sug.group_id id_group, su.username',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => "se_user_group sug",
                    "condition" => "c.id = sug.company_id"
                ),
                array(
                    "type" => "left",
                    "table" => "se_user su",
                    "condition" => "c.id = su.id_company"
                )
            )
        );
    }

    // получить контакты
    public function getContacts($idCompany)
    {
        $u = new DB('company_person', 'cp');
        $u->select('p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) fullName');
        $u->innerJoin('person p', 'p.id = cp.id_person');
        $u->where('cp.id_company = ?', $idCompany);
        $u->orderBy('cp.id');
        return $u->getList();
    }

    // получить заказы
    public function getOrders($idCompany)
    {
       return (new Order())->fetchByCompany($idCompany);
    }

    // получить пользовательские поля
    private function getCustomFields($idCompany)
    {
        $u = new DB('shop_userfields', 'su');
        $u->select("cu.id, cu.id_company, cu.value, su.id id_userfield, 
                  su.name, su.required, su.enabled, su.type, su.placeholder, su.description, su.values, sug.id id_group, sug.name name_group");
        $u->leftJoin('company_userfields cu', "cu.id_userfield = su.id AND id_company = {$idCompany}");
        $u->leftJoin('shop_userfield_groups sug', 'su.id_group = sug.id');
        $u->where('su.data = "company"');
        $u->groupBy('su.id');
        $u->orderBy('sug.sort');
        $u->addOrderBy('su.sort');
        $result = $u->getList();

        $groups = array();
        foreach ($result as $item) {
            $isNew = true;
            $newGroup = array();
            $newGroup["id"] = $item["idGroup"];
            $newGroup["name"] = empty($item["nameGroup"]) ? "Без категории": $item["nameGroup"];
            foreach ($groups as $group)
                if ($group["id"] == $item["idGroup"]) {
                    $isNew = false;
                    $newGroup = $group;
                    break;
                }
            if ($item['type'] == "date")
                $item['value'] = date('Y-m-d', strtotime($item['value']));
            $newGroup["items"][] = $item;
            if ($isNew)
                $groups[] = $newGroup;
        }
        return $groups;
    }

    // получить добавленную информацию
    protected function getAddInfo()
    {
        $result["contacts"] = $this->getContacts($this->result["id"]);
        $result["orders"] = $this->getOrders($this->result["id"]);
        $result["customFields"] = $this->getCustomFields($this->result["id"]);
        return $result;
    }

    // сохранить пользовательские поля
    private function saveCustomFields()
    {
        if (!isset($this->input["customFields"]))
            return true;

        try {
            $idCompany = $this->input["id"];
            $groups = $this->input["customFields"];
            $customFields = array();
            foreach ($groups as $group)
                foreach ($group["items"] as $item)
                    $customFields[] = $item;
            foreach ($customFields as $field) {
                $field["idCompany"] = $idCompany;
                $u = new DB('company_userfields', 'cu');
                $u->setValuesFields($field);
                $u->save();
            }
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить доп. информацию о компании!";
            throw new Exception($this->error);
        }
    }

    // сохранить контакты
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

    // сохранить логин
    private function saveLogin()
    {
        if (!isset($this->input["username"]))
            return true;

        try {
            $idCompany = $this->input["id"];
            $userName = trim($this->input["username"]);
            $u = new DB('se_user', 'su');
            $u->where("username = '?'", $userName);
            $u->andWhere("id_company <> ?", $idCompany);
            $result = $u->fetchOne();
            if ($result) {
                $this->error = "Такой логин уже существует!";
                throw new Exception($this->error);
            }

            $u = new DB('se_user', 'su');
            $u->where("id_company = ?", $idCompany);
            $result = $u->fetchOne();
            if ($result)
                $data["id"] = $result["id"];
            $data["username"] = $userName;
            if (isset($this->input["password"]))
                $data["password"] = trim($this->input["password"]);
            $u->setValuesFields($data);
            $u->save();
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить авторизационные данные компании!";
            throw new Exception($this->error);
        }
    }

    // сохранить добавленную информацию
    protected function saveAddInfo()
    {
        return $this->saveLogin() && $this->saveContacts() && $this->saveCustomFields();
    }

}