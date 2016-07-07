<?php

    $status = array();

    $data['hostname'] = $json->hostname;
    $status['status'] = 'ok';
    $status['data'] = $data;
    outputData($status);