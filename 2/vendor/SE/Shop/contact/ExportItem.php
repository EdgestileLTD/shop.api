<?php

if (IS_EXT) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/PHPExcel/Writer/Excel2007.php';
} else {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/PHPExcel/Classes/PHPExcel/Writer/Excel2007.php';
}

$IS_OUTPUT_DATA = false;

require_once dirname(__FILE__) . '/Info.php';
$contact = $status['data']['items'][0];

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();

$sheet->setTitle('Контакт ' . $contact["fullName"] ? $contact["fullName"] : $contact["id"]);

$sheet->setCellValue("A1", 'Ид. № ' . $contact["id"]);
$sheet->getStyle('A1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet->getStyle('A1')->getFill()->getStartColor()->setRGB('EEEEEE');
$sheet->mergeCells('A1:B1');
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(50);
$sheet->setCellValue("A2", 'Ф.И.О.');
$sheet->setCellValue("B2", $contact["fullName"]);
$sheet->setCellValue("A3", 'Телефон:');
$sheet->setCellValue("B3", $contact["phone"]);
$i = 4;
if ($contact["email"]) {
    $sheet->setCellValue("A$i", 'Эл. почта:');
    $sheet->setCellValue("B$i", $contact["email"]);
    $i++;
}
if ($contact["country"]) {
    $sheet->setCellValue("A$i", 'Страна:');
    $sheet->setCellValue("B$i", $contact["country"]);
    $i++;
}
if ($contact["city"]) {
    $sheet->setCellValue("A$i", 'Город:');
    $sheet->setCellValue("B$i", $contact["city"]);
    $i++;
}
$sheet->setCellValue("A$i", 'Адрес:');
$sheet->setCellValue("B$i", $contact["address"]);
$i++;
if ($contact["docSer"]) {
    $sheet->setCellValue("A$i", 'Документ:');
    $sheet->setCellValue("B$i", $contact["docSer"] . " " . $contact["docNum"] . " " . $contact["docRegistr"]);
}

$sheet->getStyle('A1:B10')->getFont()->setSize(20);

header("Expires: Mon, 1 Apr 1974 05:00:00 GMT");
header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=order.xlsx");

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$objWriter->save('php://output');
