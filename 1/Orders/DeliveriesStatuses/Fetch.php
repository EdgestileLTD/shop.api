<?php
    $items = array();

    $status['id'] = 'Y';
    $status['name'] = 'Доставлен';
    $status['color'] = '#98FB98';
    $items[] = $status;

    $status['id'] = 'N';
    $status['name'] = 'Не доставлен';
    $status['color'] = '#FFC1C1';
    $items[] = $status;

    $status['id'] = 'M';
    $status['name'] = 'В работе';
    $status['color'] = null;
    $items[] = $status;

    $status['id'] = 'P';
    $status['name'] = 'Отправлен';
    $status['color'] = null;
    $items[] = $status;

    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = array('count'=>sizeof($items), 'items'=>$items);
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);
