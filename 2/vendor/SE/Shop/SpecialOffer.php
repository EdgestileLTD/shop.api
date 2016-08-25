<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class SpecialOffer extends Base
{
    protected $tableName = "shop_leader";

    public function save()
    {
        try {
            DB::beginTransaction();
            foreach ($this->input["ids"] as $id)
                $data[] = array('id_price' => $id);
            if (!empty($data))
                DB::insertList('shop_leader', $data, true);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся добавить спец. предложения!" : $this->error;
        }
    }

    public function delete()
    {
        try {
            DB::beginTransaction();
            $idsStr = implode(",", $this->input["ids"]);
            $u = new DB('shop_leader','sl');
            $u->where('id_price in (?)', $idsStr)->deleteList();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $this->error = empty($this->error) ? "Не удаётся удалить спец. предложения!" : $this->error;
        }
    }

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sp.id, sp.article, sp.code, sp.name, sp.price',
            "joins" => array(
                "type" => "inner",
                "table" => 'shop_price sp',
                "condition" => 'sp.id = sl.id_price'
            )
        );
    }
}