<?php

$host->setHost();
$data['count'] = 1;
$data['items'] = $items;
$status['status'] = 'ok';
$status['data'] = $items;
outputData($status);