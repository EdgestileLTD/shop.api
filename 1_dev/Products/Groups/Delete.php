<?php

function deleteGroup($id)
{
    $query = "SELECT sgt.id_child id
      FROM shop_group_tree sgt 
      INNER JOIN shop_group_tree sgt1 ON sgt.id_parent = sgt1.id_child
      WHERE sgt.id_parent = {$id}";

    $ids = [];
    $res = se_db_query($query);
    while ($row = mysqli_fetch_assoc($res)) {
        $ids[] = $row["id"];
    }


    foreach ($ids as $id) {

        for ($i = 0; $i < 100; $i++) {

            $t = new seTable("shop_group_tree", "sgt");
            $t->where("id_child = ?", $id)->deleteList();

            $t = new seTable("shop_group_tree", "sgt");
            $t->where("id_parent = ?", $id)->deleteList();

            $t = new seTable("shop_group_tree", "sgt");
            $t->select("sgt.id");
            $t->where("sgt.id_child = ?", $id);
            $t->orWhere("sgt.id_parent = ?", $id);
            $result = $t->fetchOne();
            if (empty($result))
                break;
        }
    }

    foreach ($ids as $id) {

        $query = "DELETE FROM shop_price INNER JOIN shop_price_group ON shop_price.id = shop_price_group.id_price 
                    WHERE shop_price_group.id_group = {$id}";
        se_db_query($query);

        $t = new seTable("shop_price_group", "spg");
        $t->where("id_group = ?", $id)->deleteList();

        $t = new seTable("shop_group", "sg");
        $t->where("id = ?", $id)->deleteList();
    }
}

if ($json->ids) {
    $ids = implode(",", $json->ids);
    foreach ($json->ids as $id)
        deleteGroup($id);
}

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся удалить группу товаров!';
}

outputData($status);



