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

function getListContacts($id)
{
    $u = new seTable('company_person', 'cp');
    $u->select('p.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) fullName');
    $u->innerJoin('person p', 'p.id = cp.id_person');
    $u->where('cp.id_company = ?', $id);
    $u->orderby('cp.id');
    $result = $u->getList();
    $contacts = array();
    foreach ($result as $item) {
        $contact['id'] = $item['id'];
        $contact['fullName'] = $item['fullName'];
        $contact['firstName'] = $item['first_name'];
        $contact['secondName'] = $item['sec_name'];
        $contact['lastName'] = $item['last_name'];
        $contact['email'] = $item['email'];
        $contact['phone'] = $item['phone'];
        $contacts[] = $contact;
    }
    return $contacts;
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

$u = new seTable('company', 'c');
$u->select('c.*');
$u->where("c.id in ($ids)");
$result = $u->getList();

$status = array();
$items = array();

foreach ($result as $item) {
    $company = null;
    $company['id'] = $item['id'];
    $company['regDate'] = date('Y-m-d', strtotime($item['reg_date']));
    $company['inn'] = $item['inn'];
    $company['name'] = $item['name'];
    $company['email'] = $item['email'];
    $company['phone'] = $item['phone'];
    $company['note'] = $item['note'];
    $company['address'] = $item['address'];
    $company['contacts'] = getListContacts($company["id"]);
    $items[] = $company;
}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить информацию о компании!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);