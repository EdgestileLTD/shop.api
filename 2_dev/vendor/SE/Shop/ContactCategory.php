<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ContactCategory extends Base
{
    protected $tableName = "se_group";

    static public function getIdsBooksByIdGroups($idsGroups)
    {
        $idsBooks = [];
        if (!empty($idsGroups)) {
            $u = new DB('se_group', 'sg');
            $u->select("email_settings");
            $u->where('id IN (?)', implode(",", $idsGroups));
            $list = $u->getList();
            foreach ($list as $value) {
                $data = json_decode($value["emailSettings"], true);
                if (!empty($data["idBook"]))
                    $idsBooks[] = $data["idBook"];
            }
        }
        return $idsBooks;
    }

    public function fetch()
    {
        try {
            $u = new DB('se_group', 'sg');
            $u->select('sg.*, (SELECT COUNT(*) FROM se_user_group WHERE group_id=sg.id) user_count');
            $u->where('sg.title IS NOT NULL AND  sg.name <> "" AND sg.name IS NOT NULL');
            $this->result["items"] = $u->getList();
            $this->result["count"] = count($this->result["items"]);
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список групп контактов!";
        }
    }

    public function correctValuesBeforeSave()
    {
        $this->input["title"] = $this->input["name"];
    }

    public function save()
    {
        $result = parent::save();
        if ($this->isNew) {
            $emailService = new EmailProvider();
            if ($idBook = $emailService->createAddressBook($this->input["name"])) {
                $data["id"] = $this->input["id"];
                $data["emailSettings"] = json_encode(["idBook" => $idBook]);
                $u = new DB("se_group");
                $u->setValuesFields($data);
                $u->save();
            }
        }
        return $result;
    }

    public function delete()
    {
        $group = null;
        if ($this->input["ids"]) {
            $idGroup = $this->input["ids"][0];
            $group = $this->info($idGroup);
        };
        if (parent::delete() && !empty($group["emailSettings"])) {
            if ($data = json_decode($group["emailSettings"], true)) {
                $emailService = new EmailProvider();
                $emailService->removeAddressBook($data["idBook"]);
            }
        }
    }
}