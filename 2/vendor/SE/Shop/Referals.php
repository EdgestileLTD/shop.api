<?php
namespace SE\Shop;

use SE\DB;
use SE\Exception;

class Referals extends Base
{
    protected $tableName = "person";

    public function fetch($id)
    {
        $id = intval(empty($id) ? $this->input["id"] : $id);
        $u = new DB('person', 'p');
        $u->select('p.id, u.username, p.id_up, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) ref_name,' .
            'p.email, p.phone, (SELECT COUNT(*) FROM person WHERE id_up=p.id) ref_count, p.reg_date');
        $u->innerjoin('se_user u', 'u.id=p.id');
        $u->where('p.id_up=?', $id);
        $u->orderBy('p.id', 1);
        $this->result["items"] =  $u->getList($this->limit, $this->offset);
        return $this->result["items"];
    }

}