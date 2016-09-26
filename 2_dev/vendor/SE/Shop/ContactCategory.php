<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class ContactCategory extends Base
{
    protected $tableName = "se_group";

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
            $emailService->createAddressBook($this->input);
        }
        return $result;
    }
}