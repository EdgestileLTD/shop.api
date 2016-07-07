<?php

    $u = new seTable('user_admin','ua');
    $u->select('p.*, su.username, su.is_active, su.is_super_admin');
    $u->innerjoin('se_user su','su.id=ua.id_author');
    $u->innerjoin('person p','p.id=ua.id_author');
    $u->orderby('ua.id');

    $count = $u->getListCount();
    $result = $u->getList();
    foreach($result as $item) {
        $manager = null;
        $manager['id'] = $item['id'];
        $manager['isActive'] = $item['is_active'] == 'Y';
        $manager['regDate'] = date('Y-m-d', strtotime($item['reg_date']));
        $manager['firstName'] = $item['first_name'];
        $manager['secondName'] = $item['sec_name'];
        $manager['lastName'] = $item['last_name'];
        $manager['login'] = $item['username'];
        $manager['title'] = $item['last_name'].' '.$item['first_name'].' '.$item['sec_name'];
        $items[] = $manager;
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>count($items), 'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);