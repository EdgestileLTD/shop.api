<?php

namespace SE\Shop;

use SE\Exception;
use SE\DB;

class PermissionRole extends Base
{
    protected $tableName = "permission_role";
    
    private function getPermissions()
    {
        $idRole = $this->input["id"];

        try {
            $u = new DB('permission_object', 'po');
            $u->select('por.id, po.id id_object, po.name, por.mask');
            $u->leftJoin('permission_object_role por', "por.id_object = po.id AND por.id_role = {$idRole}");
            $u->groupBy('po.id');
            $u->orderBy('name');
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список прав!";
            throw new Exception($this->error);
        }
    }

    protected function getAddInfo()
    {
        $result["permissions"] = $this->getPermissions();
        return $result;
    }

    private function savePermissions()
    {
        try {
            $idRole = $this->input["id"];
            $permissions = $this->input["permissions"];
            foreach ($permissions as $permission) {
                $u = new DB("permission_object_role");
                $permission["idRole"] = $idRole;
                $permission["mask"] = (int) $permission["mask"];
                $u->setValuesFields($permission);
                $u->save();
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить права для роли!";
            throw new Exception($this->error);
        }
    }

    protected function saveAddInfo()
    {
        $this->savePermissions();
        return true;
    }

}
