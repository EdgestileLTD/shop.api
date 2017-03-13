<?php

    function getTemplates($idGroup, $temps) {
        $result = array();
        foreach ($temps as $temp) {
            if ($temp['shop_mail_group_id'] === $idGroup) {
                $letter = null;
                $letter['id'] = $temp['id'];
                $letter['idGroup'] = $temp['shop_mail_group_id'];
                $letter['name'] = $temp['title'];
                $letter['code'] = $temp['mailtype'];
                $letter['subject'] = $temp['subject'];
                $letter['letter'] = $temp['letter'];
                $letter['sortIndex'] = $temp['itempost'];
                $result[] = $letter;
            }
        }
        return $result;
    }

    $u = new seTable('shop_mail_group','smg');
    $u->select('smg.*');
    $u->orderby('id');
    $items = $u->getList();

    $v = new seTable('shop_mail','sm');
    $v->select('sm.*');
    $u->orderby('itempost');
    $objects = $v->getList();

    $groups = array();
    foreach($items as $item) {
        $group = null;
        $group['id'] = $item['id'];
        $group['name'] = $item['name'];
        $group['templates'] = getTemplates($item['id'], $objects);
        $groups[] = $group;
    }
    $data['count'] = sizeof($objects);
    $data['items'] = $groups;

    $status = array();
    if (!mysql_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }
    outputData($status);