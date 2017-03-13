<?php

$u = new seTable('se_group', 'sg');
$u->select('sg.*, sg.title AS `name`, (SELECT COUNT(*) FROM se_user_group WHERE group_id=sg.id) AS `usercount`');
$u->where('sg.title IS NOT NULL AND  sg.name <> "" AND sg.name IS NOT NULL');

$objects = $u->getList();
$data['count'] = sizeof($objects);
if (empty($data['name'])) $data['name'] = $data['title'];
$data['items'] = $objects;

$column['name'] = "name";
$column['title'] = "Наименование группы";
$column['align'] = 'right';
$columns[] = $column;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
    $status['structure']['columns'] = $columns;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить список групп контактов!';
}
outputData($status);


