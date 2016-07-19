<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

class PermissionUser extends Base
{
    protected $tableName = "se_user";

    public function fetch()
    {
        
    }

    public function delete()
    {
        try {
            $ids = $this->input["ids"];
            if ($ids) {
                $idsStr = implode(",", $ids);
                DB::query("UPDATE se_user SET is_manager = 0 WHERE id IN ({$idsStr})");
            }
        } catch (Exception $e) {
            $this->error = "Не удаётся исключить контакты из пользователей!";
        }
    }
}
