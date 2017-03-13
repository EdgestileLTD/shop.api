<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
$idsStr = array();
foreach ($ids as $id) {
    $index  = strrpos($id, "_");
    if ($index)
        $idsStr[] = substr($id, $index + 1, strlen($id) - $index);
    else $idsStr[] = $id;
}
if (!$isNew)
    $idsStr = implode(",", $idsStr);

$u = new seTable('shop_section_page', 'ssp');

if ($isNew || !empty($ids)) {
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'title');
    $isUpdated |= setField($isNew, $u, $json->isActive, 'enabled');

    if ($isUpdated){
        if (!empty($idsStr)) {
            if ($idsStr != "all")
                $u->where('id in (?)', $idsStr);
            else $u->where('true');
        }
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }
}

$data['id'] = $id;
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся сохранить информацию о разделе';
}

outputData($status);
