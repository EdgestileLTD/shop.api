<?php

if (IS_EXT) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel/Writer/Excel2007.php';
} else {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';
}

$IS_OUTPUT_DATA = false;

require_once dirname(__FILE__) . '/Fetch.php';

$groups = $status['data']['items'];

$fields = ["Ид.", "Код (URL)", "Ид. надгруппы", "Наименование", "Краткое описание",
    "Полное описание", "Фото", "Тег title", "Мета-тег keywords", "Мета-тег description"];
$fieldsTable = ["id", "code", "idParent", "name", "description",
    "fullDescription", "imageFile", "seoHeader", "seoKeywords", "seoDescription"];
$fields = array_combine($fieldsTable, $fields);

$cols = array();
for ($i = 65; $i <= 90; $i++)
    $cols[] = chr($i);

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('Группы товаров');

$i = 0;
$row = 1;
foreach ($fields as $field)
    $sheet->setCellValue("{$cols[$i++]}{$row}", $field);

$row++;
foreach ($groups as $group) {
    $i = 0;
    foreach ($fields as $key => $field)
        $sheet->setCellValue("{$cols[$i++]}{$row}", $group[$key]);
    $row++;
}

$i = 0;
foreach ($fields as $field)
    $sheet->getColumnDimension("{$cols[$i++]}")->setAutoSize(true);


header("Expires: Mon, 1 Apr 1974 05:00:00 GMT");
header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=groups.xlsx");

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$objWriter->save('php://output');





