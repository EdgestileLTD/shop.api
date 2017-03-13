<?php
    $datlist = array();
    for($i=0; $i<31; $i++){
        $datlist[] = date('Y-m-d', mktime(0,0,0,date('m'), date('d')-$i, date('Y')));
    }
    //print_r($datlist2);
    //exit;
    $u = new seTable('person','p');
    $u->select("DATE_FORMAT(p.reg_date, '%Y-%m-%d') AS dateReg, COUNT(*) AS `userCount`");
    $u->groupBy('`dateReg`');
    $u->orderBy('`dateReg`', true);
    $count = $u->getListCount();
    $result = $u->getList(0, 30);
    $items = array();
    $st = 0;
    foreach($datlist as $key=>$d){
       $items[$key]['dateReg'] = $d;
       $items[$key]['userCount'] = 0;
       foreach($result as $res){
          if ($res['dateReg']==$d){
              $items[$key]['userCount'] = $res['userCount'];
              $st += $res['userCount'];
              break; 
          }
       }
    }
    $st = $st / 30;
    $data['count'] = 30;
    $data['items'] = $items;
    $data['sr'] = $st;
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }
    outputData($status);