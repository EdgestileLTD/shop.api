<?php

$type['id'] = "simple";
$type['code'] = "simple";
$type['name'] = "Простая доставка";
$types[] = $type;

$type = null;
$type['id'] = "region";
$type['code'] = "region";
$type['name'] = "Доставка по регионам";
$type['isTreeMode'] = true;         // позволять создовать дочерние доставки
$type['isNeedRegion'] = true;       // позволять создовать список регионов доставок
$type['isNeedConditions'] = true;   // позволять создовать список условий доставок
$types[] = $type;

$type = null;
$type['id'] = "subregion";
$type['code'] = "subregion";
$type['name'] = "Доставка по регионам с подпунктами";
$type['isTreeMode'] = true;         // позволять создовать дочерние доставки
$type['isNeedRegion'] = true;       // позволять создовать список регионов доставок
$type['isNeedConditions'] = true;   // позволять создовать список условий доставок
$types[] = $type;


$type = null;
$type['id'] = "ems";
$type['code'] = "ems";
$type['name'] = "EMS (калькулятор)";
$types[] = $type;

$type = null;
$type['id'] = "post";
$type['code'] = "post";
$type['name'] = "Почта России";
$types[] = $type;

$type = null;
$type['id'] = "sdek";
$type['code'] = "sdek";
$type['name'] = "СДЭК";
$types[] = $type;

$data['count'] = sizeof($types);
$data['items'] = $types;

$status = array();
$status['status'] = 'ok';
$status['data'] = $data;
outputData($status);
