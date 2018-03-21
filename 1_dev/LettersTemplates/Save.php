<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_mail', 'sm');

if ($isNew || !empty($ids)) {

    if (!empty($json->letter))
        $json->letter = str_replace('src="/images', 'src="http://' . $json->hostname . '/images', $json->letter);


    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->idGroup, 'shop_mail_group_id');
    $isUpdated |= setField($isNew, $u, $json->name, 'title');
    $isUpdated |= setField($isNew, $u, $json->code, 'mailtype');
    $isUpdated |= setField($isNew, $u, $json->subject, 'subject');
    $isUpdated |= setField($isNew, $u, $json->letter, 'letter');

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