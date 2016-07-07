<?php

function getUserName($lastName, $userName, $id = 0)
{
    if (empty($userName))
        $userName = strtolower(rus2translit($lastName));
    $username_n = $userName;

    $u = new seTable('se_user', 'su');
    $i = 2;
    while ($i < 1000) {
        if ($id)
            $u->findlist("su.username='$username_n' AND id <> $id")->fetchOne();
        else $u->findlist("su.username='$username_n'")->fetchOne();
        if ($u->id)
            $username_n = $userName . $i;
        else return $username_n;
        $i++;
    }
    return uniqid();
}

function saveGroups($newIdsGroups, $idsContact)
{
    $idsContactsS = implode(",", $idsContact);
    $idsGroupsS = implode(",", $newIdsGroups);
    $u = new seTable('se_user_group', 'sug');
    if ($newIdsGroups)
        $u->where("NOT group_id IN ($idsGroupsS) AND user_id IN ($idsContactsS)")->deleteList();
    else $u->where("user_id IN ($idsContactsS)")->deleteList();
    $u = new seTable('se_user_group', 'sug');
    $u->select("group_id");
    $u->where("user_id IN ($idsContactsS)");
    $objects = $u->getList();
    $idsExists = array();
    foreach ($objects as $object)
        $idsExists[] = $object["group_id"];
    if (!empty($newIdsGroups)) {
        foreach ($newIdsGroups as $id)
            if (!empty($id) && !in_array($id, $idsExists))
                foreach ($idsContact as $idContact)
                    $data[] = array('user_id' => $idContact, 'group_id' => $id);
        if (!empty($data))
            se_db_InsertList('se_user_group', $data);
    }
}

function saveCompanyRequisites($id, $json)
{
    $u = new seTable('user_urid', 'uu');
    $u->find($id);

    $u->id = $id;
    if (isset($json->companyName))
        $u->company = $json->companyName;
    if (isset($json->companyDirector))
        $u->director = $json->companyDirector;
    if (isset($json->companyPhone))
        $u->tel = $json->companyPhone;
    if (isset($json->companyFax))
        $u->fax = $json->companyFax;
    if (isset($json->companyOfficialAddress))
        $u->uradres = $json->companyOfficialAddress;
    if (isset($json->companyMailAddress))
        $u->fizadres = $json->companyMailAddress;

    $u->save();

    if (isset($json->companyRequisites)) {
        $u = new seTable('user_rekv', 'ur');
        $u->where('id_author=?', $id)->deletelist();

        foreach ($json->companyRequisites as $requisite)
            $data[] = array('id_author' => $id, 'rekv_code' => $requisite->code,
                'value' => $requisite->value);
        if (!empty($data))
            se_db_InsertList('user_rekv', $data);
    }
}

function savePersonalAccounts($id, $accounts)
{

    $idsUpdate = null;
    foreach ($accounts as $account)
        if ($account->id) {
            if (!empty($idsUpdate))
                $idsUpdate .= ',';
            $idsUpdate .= $account->id;
        }

    $u = new seTable('se_user_account', 'sua');
    if (!empty($idsUpdate))
        $u->where("NOT id IN ($idsUpdate) AND user_id=?", $id)->deletelist();
    else $u->where("user_id=?", $id)->deletelist();

    $data = array();
    foreach ($accounts as $account)
        if (empty($account->id))
            $data[] = array("user_id" => $id, "date_payee" => $account->datePayee,
                "in_payee" => $account->inPayee, "out_payee" => $account->outPayee,
                "curr" => $account->currency, "operation" => $account->typeOperation, "docum" => $account->note);
    if ($data)
        se_db_InsertList('se_user_account', $data);

    foreach ($accounts as $account)
        if (!empty($account->id)) {
            $u = new seTable('se_user_account', 'sua');
            $isUpdated = false;
            $isUpdated |= setField(0, $u, $account->datePayee, 'date_payee');
            $isUpdated |= setField(0, $u, $account->typeOperation, 'operation');
            $isUpdated |= setField(0, $u, $account->inPayee, 'in_payee');
            $isUpdated |= setField(0, $u, $account->outPayee, 'out_payee');
            $isUpdated |= setField(0, $u, $account->typeOperation, 'operation');
            $isUpdated |= setField(0, $u, $account->note, 'docum');
            if ($isUpdated) {
                $u->where('id=?', $account->id);
                $u->save();
            }
        }
}

function setUserGroup($idUser)
{
    $u = new seTable('se_group', 'sg');
    $u->select("id");
    $u->where("title = 'User'");
    $u->fetchOne();
    $idGroup = $u->id;
    if (!$idGroup) {
        $u = new seTable('se_group', 'sg');
        $u->title = "User";
        $u->level = 1;
        $idGroup = $u->save();
    }

    $u = new seTable('se_user_group', 'sug');
    $u->select("id");
    $u->where("sug.group_id = {$idGroup} AND sug.user_id = {$idUser}");
    $u->fetchOne();
    $id = $u->id;
    if (!$id) {
        $u = new seTable('se_user_group', 'sug');
        $u->group_id = $idGroup;
        $u->user_id = $idUser;
        $u->save();
    }
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$isNew = empty($ids);
if (!$isNew)
    $idsStr = implode(",", $ids);

$userName = isset($json->login) ? $json->login : null;
if ($isNew) {
    $login = !empty($json->lastName) ? trim($json->lastName) : $json->firstName;
    $userName = getUsername($login, $userName);
    if (!empty($userName)) {
        $u = new seTable('se_user', 'su');
        $u->username = $userName;
        setField($isNew, $u, $json->passwordHash, 'password');
        if (isset($json->isActive)) {
            if ($json->isActive)
                setField($isNew, $u, "Y", 'is_active');
            else setField($isNew, $u, "N", 'is_active');
        }
        $ids[] = $u->save();
    }
} else {
    $u = new seTable('se_user', 'su');
    $isUpdated = false;
    if (!empty($userName)) {
        $userName = getUsername($json->lastName, $userName, $ids[0]);
        $isUpdated |= setField($isNew, $u, $userName, 'username');
    }
    $isUpdated |= setField($isNew, $u, $json->passwordHash, 'password');
    if (isset($json->isActive)) {
        if ($json->isActive)
            $isUpdated |= setField($isNew, $u, "Y", 'is_active');
        else $isUpdated |= setField($isNew, $u, "N", 'is_active');
    }
    if ($isUpdated) {
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        $idv = $u->save();
        if ($isNew)
            $ids[] = $idv;
    }

}

if ($isNew || !empty($ids)) {
    $u = new seTable('person', 'p');

    if ($isNew) {
        $u->id = $ids[0];
        $u->reg_date = date("Y-m-d H:i:s");
    }

    $isUpdated = false;
    $isUpdated |= setField($isNew, $u, $json->lastName, 'last_name');
    $isUpdated |= setField($isNew, $u, $json->firstName, 'first_name');
    $isUpdated |= setField($isNew, $u, $json->secondName, 'sec_name');
    $isUpdated |= setField($isNew, $u, $json->gender, 'sex');
    $isUpdated |= setField($isNew, $u, $json->regDate, 'reg_date');
    $isUpdated |= setField($isNew, $u, $json->loyalty, 'loyalty');
    $isUpdated |= setField($isNew, $u, $json->imageFile, 'avatar');
    $isUpdated |= setField($isNew, $u, $json->email, 'email');
    $isUpdated |= setField($isNew, $u, $json->skype, 'scype');
    $isUpdated |= setField($isNew, $u, $json->phone, 'phone');
    $isUpdated |= setField($isNew, $u, $json->postIndex, 'post_index');
    $isUpdated |= setField($isNew, $u, $json->address, 'addr');
    $isUpdated |= setField($isNew, $u, $json->birthDate, 'birth_date');
    $isUpdated |= setField($isNew, $u, $json->discount, 'discount');
    $isUpdated |= setField($isNew, $u, $json->note, 'note');
    $isUpdated |= setField($isNew, $u, $json->docSer, 'doc_ser');
    $isUpdated |= setField($isNew, $u, $json->docNum, 'doc_num');
    $isUpdated |= setField($isNew, $u, $json->country, 'country', 'integer default 0', 1);
    $isUpdated |= setField($isNew, $u, $json->city, 'city', 'varchar(60)', 1);
    $isUpdated |= setField($isNew, $u, $json->docRegistr, 'doc_registr');
    $isUpdated |= setField($isNew, $u, $json->isRead, 'is_read', 'tinyint(1) default 0', 1);
    $isUpdated |= setField($isNew, $u, $json->emailValid, 'email_valid', "enum('Y','N','C') default 'C'", 1);
    $isUpdated |= setField($isNew, $u, $json->email_valid, 'email_valid', "enum('Y','N','C') default 'C'", 1);
    $isUpdated |= setField($isNew, $u, $json->request, 'request', 'tinyint(1) default 0', 1);
    $isUpdated |= setField($isNew, $u, $json->question, 'question', "text", 0);
    $isUpdated |= setField($isNew, $u, $json->isWholesaler, 'is_wholesaler');

    if ($isUpdated) {
        if (!empty($idsStr))
            $u->where('id in (?)', $idsStr);
        $u->save();
    }

    saveCompanyRequisites($ids[0], $json);
    if ($ids && isset($json->personalAccount))
        savePersonalAccounts($ids[0], $json->personalAccount);
    if (isset($json->isAdmin) && $json->isAdmin)
        $json->idsGroups[] = 3;
    if ($ids && isset($json->idsGroups))
        saveGroups($json->idsGroups, $ids);
    else {
        if (isset($json->isAdmin) && !$json->isAdmin) {
            $u = new seTable('se_user_group', 'sug');
            $u->where('group_id=3 AND user_id=?', $ids[0])->deletelist();
        }
    }

    setUserGroup($ids[0]);
}

$data['id'] = $ids[0];
$status = array();

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся сохранить контакт!';
}

outputData($status);
