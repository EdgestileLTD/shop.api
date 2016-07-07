<?php

function getCode($id, $title, $code)
{
    if (empty($code))
        $code = strtolower(se_translite_url($title));
    $u = new seTable('shop_brand', 'sb');
    $i = 2;
    $code_n = $code;
    while ($i < 1000) {
        $u->select('sb.id, sb.code');
        $s = "sb.code='$code_n'";
        if ($id)
            $s .= " AND sb.id<>$id";
        $u->findlist($s)->fetchOne();
        if ($u->id)
            $code_n = $code . $i;
        else return $code_n;
        $i++;
    }
}

$isNew = empty($json->id);

$code = getCode($json->id, $json->name, $json->code);
$u = new seTable('shop_brand', 'sb');

if (!$isNew)
    $u->find($json->id);

if ($isNew || $u->id) {
    $u->name = $json->name;
    $u->code = $code;
    if (isset($json->imageFile))
        $u->image = $json->imageFile;
    if (isset($json->description))
        $u->text = $json->description;
    if (isset($json->seoHeader))
        $u->title = $json->seoHeader;
    if (isset($json->seoKeywords))
        $u->keywords = $json->seoKeywords;
    if (isset($json->seoDescription))
        $u->description = $json->seoDescription;
    if ($isNew)
        $id = $u->save();
    else {
        if ($u->save())
            $id = $u->id;
    }
} else $id = "";

$data['id'] = $id;
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить информация о бренде!';
}

outputData($status);
