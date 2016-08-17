<?php

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

function existINN($inn, $id)
{
    $u = new seTable('company', 'c');
    $u->where("inn = '?'", $inn);
    if ($id)
        $u->andWhere("id <> ?", $id);
    $u->fetchOne();
    return $u->isFind();
}

function saveContacts($idCompany, $contacts)
{
    $idsContacts = array();
    foreach ($contacts as $contact)
        $idsContacts = $contact->id;
    $idsContactsStr = implode(",", $idsContacts);
    $u = new seTable('company_person', 'cp');
    if (empty($idsContacts))
        $u->where("id_company = ?", $idCompany)->deleteList();
    else $u->where("id_company = ? AND NOT id_person IN ({$idsContactsStr})", $idCompany)->deleteList();
    $idsExist = array();
    $u = new seTable('company_person', 'cp');
    $u->select("id_person");
    $u->where("id_company = ?", $idCompany);
    $result = $u->getList();
    foreach ($result as $item)
        $idsExist[] = $item["id_person"];
    $data = array();
    foreach ($contacts as $contact)
        if (!in_array($contact->id, $idsExist))
            $data[] = array("id_person" => $contact->id, "id_company" => $idCompany);
    if (!empty($data))
        se_db_InsertList("company_person", $data);
}

if ($json->inn && existINN($json->inn, $ids[0])) {
    $status['status'] = 'error';
    $status['errortext'] = 'Компания с указанным ИНН уже существует!';
    outputData($status);
    exit;
}

if ($isNew || !empty($ids)) {
    $u = new seTable('company', 'c');
    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->name, 'name');
    $isUpdated |= setField($isNew, $u, $json->inn, 'inn');
    $isUpdated |= setField($isNew, $u, $json->phone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->email, 'email');
    $isUpdated |= setField($isNew, $u, $json->address, 'address');
    $isUpdated |= setField($isNew, $u, $json->note, 'note');

    if ($isUpdated) {
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        if ($isNew)
            $ids[0] = $u->save();
        else $u->save();
        if ($ids[0] && isset($json->contacts))
            saveContacts($ids[0], $json->contacts);
    }
}

$data['id'] = $ids[0];
$status = array();

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить компанию!';
}

outputData($status);
