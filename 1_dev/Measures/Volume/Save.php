<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 17.06.2017
 * Time: 13:31
 */

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_measure_volume', 'smv');

if ($isNew || !empty($ids)) {
    $isUpdated = false;

    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->designation, 'designation');
    $isUpdated |= setField($isNew, $u, $json->code, 'code');
    $isUpdated |= setField($isNew, $u, (float)$json->value, 'value');
    $isUpdated |= setField($isNew, $u, (int)$json->is_base, 'is_base');
    $isUpdated |= setField($isNew, $u, (int)$json->precision, '`precision`');

    if ($json->is_base)
        se_db_query("UPDATE shop_measure_volume SET is_base = FALSE");

    if ($isUpdated) {
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
            else $u->where('true');
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }
};

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = se_db_error();
}

outputData($status);