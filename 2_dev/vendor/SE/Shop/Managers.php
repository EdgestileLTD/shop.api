<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Managers extends Base
{

    protected $tableName = "se_user";


    public function fetch()
    {
        try {
            $u = new DB('se_user', 'su');
            $u->select('su.id, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) name');
            $u->innerJoin('person p', 'p.id = su.id');
            $u->where('(su.is_manager=1 OR su.is_super_admin=1)');
            $u->andWhere('su.is_active=1');
            $u->orderBy('su.id');
            $u->groupBy('su.id');

            $items = array();
            $result = $u->getList();
            foreach ($result as $item) {
                $items[$item['id']] = trim($item['name']);
            }
            $this->result["items"] = $items;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список менеджеров!";
        }
    }
}