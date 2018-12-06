<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class PermissionUser extends Base
{
    protected $tableName = "se_user";


    public function fetch()
    {
        try {
            $u = new DB('se_user', 'su');
            $u->select('su.id, p.reg_date, p.first_name, p.sec_name, p.last_name,                 
                su.username, su.is_active, su.is_super_admin,
                GROUP_CONCAT(pru.id_role SEPARATOR ",") idsRoles,
                GROUP_CONCAT(pr.name ORDER BY pr.name SEPARATOR ", ") roles');
            $u->innerJoin('person p', 'p.id = su.id');
            $u->leftJoin('permission_role_user pru', 'pru.id_user = su.id');
            $u->leftJoin('permission_role pr', 'pr.id = pru.id_role');
            $u->where('su.is_manager');
            $u->orderBy('su.id');
            $u->groupBy('su.id');

            $items = array();
            $count = $u->getListCount();
            $result = $u->getList();
            foreach ($result as $item) {
                $manager = null;
                $manager['id'] = $item['id'];
                $manager['isActive'] = $item['isActive'] == 'Y';
                $manager['regDate'] = date('Y-m-d', strtotime($item['regDate']));
                $manager['firstName'] = $item['firstName'];
                $manager['secondName'] = $item['secName'];
                $manager['lastName'] = $item['lastName'];
                $manager['login'] = $item['username'];
                $manager['title'] = $item['lastName'] . ' ' . $item['firstName'] . ' ' . $item['secName'];
                $manager['idsRoles'] = $item['idsRoles'];
                $manager['roles'] = $item['roles'];
                $items[] = $manager;
            }
            $this->result["items"] = $items;
            $this->result["count"] = $count;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список прав пользователя!";
        }
    }

    public function delete()
    {
        try {
            if ($this->input["allMode"]) {
                DB::query("UPDATE se_user SET is_manager = 0"); 
            } else {
              $ids = $this->input["ids"];
              if ($ids) {
                $idsStr = implode(",", $ids);
                DB::query("UPDATE se_user SET is_manager = 0 WHERE id IN ({$idsStr})"); 
              }
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся исключить контакты из пользователей!";
        }
    }

    public function save($isTransactionMode = true)
    {
        $idsStr = null;
        $ids = $this->input["ids"];
        $isNew = empty($ids);
        if (!$isNew)
            $idsStr = implode(",", $ids);
        try {
            if ($ids) {
                if (!empty($this->input["idsRoles"])) {
                    $idsRoles = implode(",", $this->input["idsRoles"]);
                    $u = new DB("permission_role_user", "pru");
                    $u->where("id_user IN (?)", $idsStr);
                    $u->andWhere("NOT id_role IN (?)", $idsRoles);
                    $u->deleteList();
                    foreach ($ids as $id)
                        foreach ($this->input["idsRoles"] as $idRole) {
                            $sql = "INSERT IGNORE INTO permission_role_user (id_user, id_role) VALUE ({$id}, {$idRole})";
                            DB::exec($sql);
                        }
                } else DB::exec("UPDATE se_user SET is_manager = 1 WHERE id IN ({$idsStr})");
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить роли для пользователей!";
        }
    }
}
