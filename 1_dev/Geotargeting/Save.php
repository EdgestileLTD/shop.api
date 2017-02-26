<?php

function getSortIndex()
{
    $u = new seTable('shop_contacts', 'sс');
    $u->select('MAX(sс.sort) sort');
    $u->fetchOne();
    return $u->sort + 1;
}

function saveIdCity($idContact, $idCity)
{
    $u = new seTable('shop_contacts_geo', 'sсg');
    $u->select('id');
    $u->where("scg.id_contact = {$idContact} AND scg.id_city = {$idCity}");
    $u->fetchOne();
    $u->id_contact = $idContact;
    $u->id_city = $idCity;
    $u->save();
}

function saveVariables($idContact, $variables)
{
    foreach ($variables as $variable) {
        $t = new seTable('shop_geo_variables');
        if ($variable->id) {
            $t->where("id = ?", $variable->id);
            $t->fetchOne();
        } else {
            $t->id_contact = $idContact;
            $t->id_variable = $variable->idVariable;
        }
        $t->value = $variable->value;
        $t->save();
    }
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$u = new seTable('shop_contacts', 'sс');


if ($isNew || !empty($ids)) {
    $isUpdated = false;
    if ($isNew)
        $json->sortIndex = getSortIndex();
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->address, 'address');
    $isUpdated |= setField($isNew, $u, $json->phone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->additionalPhones, 'additional_phones');
    $isUpdated |= setField($isNew, $u, $json->description, 'description');
    $isUpdated |= setField($isNew, $u, $json->isActive, 'is_visible');
    $isUpdated |= setField($isNew, $u, $json->sortIndex, 'sort');
    $isUpdated |= setField($isNew, $u, $json->image, 'image');
    $isUpdated |= setField($isNew, $u, $json->url, 'url');

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

    if (isset($json->idCity))
        saveIdCity($ids[0], $json->idCity);
    saveVariables($ids[0], $json->variables);
};

$data['id'] = $ids[0];
$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить информацию о контакте!';
}

outputData($status);

