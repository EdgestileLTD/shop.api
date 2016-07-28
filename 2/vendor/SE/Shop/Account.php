<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Account extends Base
{
    protected $tableName = "accounts";
    protected $sortOrder = "asc";

    public function fetch()
    {
        $items = array();
        $project = str_replace(".e-stile.ru", "", HOSTNAME);
        $items[] = array("alias" => $project, "project" => $project, "login" => $_SESSION["login"],
            "hash" => $_SESSION["hash"], "isMain" => true);
        try {
            $db = new DB("$this->tableName");
            $db->select("a.*");
            $result = $db->getList();
            foreach ($result as $item) {
                $item["isMain"] = false;
                $item["alias"] = empty($item["alias"]) ? $item["project"] : $item["alias"];
                $items[] = $item;
            }
            $this->result["items"] = $items;
            $this->result["count"] = count($items);
        } catch (Exception $e) {
            $this->error = "Не удаётся получить аккаунты проекта!";
        }
    }

    public function save()
    {
        $isNew = empty($this->input["id"]);
        $mainProject = str_replace(".e-stile.ru", "", HOSTNAME);
        $this->input["project"] = str_replace(".e-stile.ru", "", $this->input["project"]);
        if ($mainProject == $this->input["project"] ) {
            $this->result = "Такой аккаунт уже существует!";
            return null;
        }
        if ($isNew) {
            try {
                $project = $this->input["project"];
                $db = new DB("$this->tableName");
                $db->select("a.*");
                $db->where("project = '{$project}'");
                if ($db->getList())
                    $this->result = "Такой аккаунт уже существует!";
                else {
                    $db = new DB("$this->tableName");
                    $db->setValuesFields($this->input);
                    $this->input["id"] = $db->save();
                }
            } catch (Exception $e) {
                $this->result = "Не удаётся добавить аккаунт!";
            }
        } else {
            try {
                $db = new DB("$this->tableName");
                $db->setValuesFields($this->input);
                $db->save();
            } catch (Exception $e) {
                $this->result = "Не удаётся обновить данные аккаунта!";
            }
        }
        $this->info();
        return $this;
    }

}