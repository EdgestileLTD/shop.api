<?php

if (empty($json->ids))
    exit;

function getPersonalAccount($id)
{
    $u = new seTable('se_user_account', 'sua');
    $u->select('id, order_id AS idOrder, account, date_payee AS datePayee, in_payee AS inPayee,
              out_payee AS outPayee, curr AS currency, operation AS typeOperation, docum AS note');
    $u->where('sua.user_id=?', $id);
    $u->orderby("sua.date_payee");
    $result = $u->getList();
    $account = array();
    $balance = 0;
    foreach ($result as $item) {
        settype($item['inPayee'], float);
        settype($item['outPayee'], float);
        settype($item['typeOperation'], int);
        $item['datePayee'] = date('Y-m-d', strtotime($item['datePayee']));
        $balance += ($item['inPayee'] - $item['outPayee']);
        $item['balance'] = $balance;
        $account[] = $item;
    }
    return $account;
}

function getCompanyRequisites($id)
{
    GLOBAL $json;

    $u = new seTable('user_rekv_type', 'urt');
    $u->select('ur.*, urt.size, urt.title');
    $u->leftjoin('user_rekv ur', 'ur.rekv_code=urt.code');
    $u->where('ur.id_author=?', $id);
    $u->groupby('urt.code');
    $u->orderby('urt.id');
    $result = $u->getList();
    $requisites = array();
    foreach ($result as $item) {
        $requisite['id'] = $item['id'];
        $requisite['code'] = $item['rekv_code'];
        $requisite['name'] = $item['title'];
        $requisite['value'] = $item['value'];
        $requisite['size'] = (int)$item['size'];
        $requisites[] = $requisite;
    }
    return $requisites;
}

function getGroups($id)
{
    $u = new seTable('se_group', 'sg');
    $u->select('sg.id, sg.title name');
    $u->innerjoin('se_user_group sug', 'sg.id = sug.group_id');
    $u->where('sg.title IS NOT NULL AND sg.name <> "" AND sg.name IS NOT NULL AND sug.user_id = ?', $id);
    return $u->getList();
}

$ids = implode(",", $json->ids);

$u = new seTable('person', 'p');
$u->select('p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) fullName,
            GROUP_CONCAT(DISTINCT(sug.group_id) SEPARATOR ";") AS idsGroups,
            su.username AS login, su.password, su.is_active, uu.company, uu.director,
            uu.tel, uu.fax, uu.uradres, uu.fizadres');
$u->leftjoin('se_user_group sug', 'p.id=sug.user_id');
$u->leftjoin('se_user su', 'p.id=su.id');
$u->leftjoin('user_urid uu', 'uu.id=su.id');
$u->where("p.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();

foreach ($result as $item) {
    $contact = null;
    $contact['id'] = $item['id'];
    $contact['regDate'] = date('Y-m-d', strtotime($item['reg_date']));
    $contact['regDateDisplay'] = date('d.m.Y', strtotime($item['reg_date']));
    $contact['login'] = $item['login'];
    $contact['passwordHash'] = $item['password'];
    $contact['isActive'] = $item['is_active'] == 'Y';
    $contact['fullName'] = $item['fullName'];
    $contact['firstName'] = $item['first_name'];
    $contact['secondName'] = $item['sec_name'];
    $contact['lastName'] = $item['last_name'];
    $contact['loyalty'] = (int)$item['loyalty'];
    $contact['gender'] = $item['sex'];
    $contact['email'] = $item['email'];
    $contact['phone'] = $item['phone'];
    $contact['note'] = $item['note'];
    $contact['skype'] = $item['scype'];
    $contact['birthDate'] = date('Y-m-d', strtotime($item['birth_date']));
    $contact['birthDateDisplay'] = date('d.m.Y', strtotime($item['birth_date']));
    $contact['postIndex'] = $item['post_index'];
    $contact['address'] = $item['addr'];
    $contact['docSer'] = $item['doc_ser'];
    $contact['docNum'] = $item['doc_num'];
    $contact['country'] = $item['country'];
    $contact['city'] = $item['city'];
    $contact['docRegistr'] = $item['doc_registr'];
    $contact['discount'] = (real)$item['discount'];
    $contact['isRead'] = $item['is_read'];
    $contact['imageFile'] = $item['avatar'];
    $contact['emailValid'] = (isset($item['email_valid'])) ? $item['email_valid'] : 'C';
    $contact['companyName'] = $item['company'];
    $contact['companyDirector'] = $item['director'];
    $contact['companyPhone'] = $item['tel'];
    $contact['companyFax'] = $item['fax'];
    $contact['companyMailAddress'] = $item['fizadres'];
    $contact['companyOfficialAddress'] = $item['uradres'];
    $contact['request'] = $item['request'];
    $contact['question'] = $item['question'];
    $contact['groups'] = getGroups($contact['id']);
    $contact['companyRequisites'] = getCompanyRequisites($contact['id']);

    $idsGroups = explode(';', $item['idsGroups']);
    foreach ($idsGroups as $idGroup) {
        $contact['idsGroups'][] = $idGroup;
        if ($idGroup == 3)
            $contact["isAdmin"] = true;
    }
    $contact['personalAccount'] = getPersonalAccount($item['id']);
    $items[] = $contact;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);