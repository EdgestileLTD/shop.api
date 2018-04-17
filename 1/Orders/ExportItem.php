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
$order = $status['data']['items'][0];

$status = array();
$deliveriesStatuses = array();
require_once dirname(__FILE__) . '/DeliveriesStatuses/Fetch.php';
$url = API_ROOT_URL . "/Orders/DeliveriesStatuses/Fetch.api";
if ($status["status"] == "ok")
    $deliveriesStatuses = $status["data"]["items"];

$status = array();
$ordersStatuses = array();
require_once dirname(__FILE__) . '/OrdersStatuses/Fetch.php';
$url = API_ROOT_URL . "/Orders/OrdersStatuses/Fetch.api";
if ($status["status"] == "ok")
    $ordersStatuses = $status["data"]["items"];

function getStatus($code, $isOrderStatus = true)
{
    global $deliveriesStatuses, $ordersStatuses;
    if ($isOrderStatus)
        $statuses = $ordersStatuses;
    else $statuses = $deliveriesStatuses;
    foreach ($statuses as $status)
        if ($status["id"] == $code)
            return $status["name"];
    return "Неизвестный";
}

$xls = new PHPExcel();
$xls->setActiveSheetIndex(0);
$sheet = $xls->getActiveSheet();
$sheet->setTitle('Заказ № ' . $order["id"]);

$sheet->setCellValue("A1", 'Заказ № ' . $order["id"] . " от " . date("d.m.Y", strtotime($order["dateOrder"])));
$sheet->getStyle('A1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet->getStyle('A1')->getFill()->getStartColor()->setRGB('EEEEEE');
$sheet->getStyle('A1')->getFont()->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$sheet->mergeCells('A1:F1');
$sheet->getColumnDimension('A')->setWidth(16);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(14);
$sheet->getColumnDimension('D')->setWidth(9);
$sheet->getColumnDimension('E')->setWidth(9);
$sheet->getColumnDimension('F')->setWidth(9);
$sheet->getColumnDimension('G')->setWidth(9);

$sheet->setCellValue("A3", '№ счёта:');
if ($order["payments"])
    $sheet->setCellValue("B3", $order["payments"][0]["docNum"]);
$sheet->setCellValue("A4", 'Дата заказа:');
$sheet->setCellValue("B4", date("d.m.Y", strtotime($order["dateOrder"])));
$sheet->setCellValue("C4", 'Статус заказа:');
$sheet->setCellValue("D4", getStatus($order["statusOrder"]));
$sheet->mergeCells('D4:F4');
$sheet->setCellValue("A5", 'Заказчик:');
$sheet->setCellValue("B5", $order["customer"]);
$sheet->setCellValue("A6", 'Телефон:');
$sheet->setCellValue("B6", $order["customerPhone"]);
$sheet->setCellValue("C6", 'Email:');
$sheet->setCellValue("D6", $order["customerEmail"]);
$sheet->mergeCells('D6:F6');
$sheet->setCellValue("A7", 'Доставка:');
$sheet->setCellValue("B7", $order["deliveryName"]);
$sheet->setCellValue("C7", 'Сумма:');
$sheet->setCellValue("D7", $order["deliverySum"]);
$sheet->mergeCells('D7:F7');
$sheet->setCellValue("A8", 'Статус:');
$sheet->setCellValue("B8", getStatus($order["statusDelivery"], false));
$sheet->setCellValue("C8", 'Дата доставки:');
$sheet->setCellValue("D8", date("d.m.Y", strtotime($order["deliveryDate"])));
$sheet->mergeCells('D8:F8');
$sheet->setCellValue("A9", 'Адрес доставки:');
$sheet->setCellValue("B9", $order["deliveryAddress"]);
$sheet->getStyle('B9')->getAlignment()->setWrapText(true);
$sheet->setCellValue("C9", 'Индекс:');
$sheet->setCellValue("D9", $order["deliveryPostIndex"]);
$sheet->mergeCells('D9:F9');
$sheet->setCellValue("A10", 'Телефон:');
$sheet->setCellValue("B10", $order["deliveryPhone"]);
$sheet->setCellValue("C10", 'Время звонка:');
$sheet->setCellValue("D10", $order["deliveryCallTime"]);
$sheet->mergeCells('D10:F10');
$sheet->setCellValue("A11", 'Примечание:');
$sheet->setCellValue("B11", $order["deliveryNote"]);
$sheet->mergeCells('B11:F11');
$sheet->setCellValue("C12", 'Сумма товаров и услуг:');
$sheet->mergeCells('C12:D12');
$sheet->setCellValue("E12", (real)($order["sum"] + $order["discountSum"] - $order["deliverySum"]));
$sheet->mergeCells('E12:F12');
$sheet->setCellValue("C13", 'Сумма скидки:');
$sheet->mergeCells('C13:D13');
$sheet->setCellValue("E13", $order["discountSum"] + $order["discountProducts"]);
$sheet->mergeCells('E13:F13');
$sheet->setCellValue("C14", 'ИТОГО:');
$sheet->mergeCells('C14:D14');
$sheet->setCellValue("E14", $order["sum"]);
$sheet->mergeCells('E14:F14');
/*
$sheet->getStyle('D7')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('E12')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('E13')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('E14')->getNumberFormat()->setFormatCode('#,##0.00');
*/
$sheet->getStyle('A5:F5')->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);
$sheet->getStyle('A7:F7')->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);
$sheet->getStyle('A12:F12')->getBorders()->getTop()->setBorderStyle(PHPExcel_Style_Border::BORDER_THICK);
$sheet->getStyle('A9:F9')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
$sheet->getStyle('A3:A15')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('C3:C15')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('B3:B15')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('D3:D15')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('E3:E15')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('A3:A11')->getFont()->setBold(true);
$sheet->getStyle('C3:C11')->getFont()->setBold(true);
$sheet->getStyle('C14:F14')->getFont()->setBold(true);
$sheet->getStyle('C12:F14')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);


$sheet->setCellValue("A17", 'Наименование товара/услуги');
$sheet->mergeCells('A17:B17');
$sheet->setCellValue("C17", 'Артикул');
$startSym = "D";
$codeSym = ord($startSym);
if ($order["items"]) {
    $product = $order["items"][0];
    foreach ($product["modifications"] as $modification)
        $sheet->setCellValue(chr($codeSym++) . "17", $modification["name"]);
}

$startSymCount = $codeSym;
$sheet->setCellValue(chr($codeSym++) . "17", 'Кол-во');
$sheet->setCellValue(chr($codeSym++) . "17", 'Цена пр.');
$sheet->setCellValue(chr($codeSym++) . "17", 'Сумма пр.');
$sheet->setCellValue(chr($codeSym++) . "17", 'Цена зак.');
$sheet->setCellValue(chr($codeSym) . "17", 'Сумма зак.');
$sheet->setCellValue("A16", 'Товары и услуги заказа');
$endSym = chr($codeSym);
$sheet->mergeCells('A16:' . $endSym . '16');
$sheet->getStyle('A16:' . $endSym . '16')->getBorders()->getBottom()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
$sheet->getStyle('A16:' . $endSym . '16')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A17:' . $endSym . '17')->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
$sheet->getStyle('A17:' . $endSym . '17')->getFont()->setBold(true);
$i = 18;
foreach ($order["items"] as $product) {
    $codeSym = ord($startSym);
    //$sheet->getStyle("E$i:" . $endSym . $i)->getNumberFormat()->setFormatCode('#,##0.00');
    $sheet->getStyle("A$i:" . $endSym . $i)->getBorders()->getAllBorders()->setBorderStyle(PHPExcel_Style_Border::BORDER_THIN);
    $sheet->getStyle("A$i:" . $endSym . $i)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_TOP);
    $sheet->mergeCells("A$i:B$i");
    $sheet->getStyle("A$i")->getAlignment()->setWrapText(true);
    if (strlen($product["originalName"]) > 50)
        $sheet->getRowDimension("$i")->setRowHeight(30);
    $sheet->setCellValue("A$i", $product["originalName"]);
    $sheet->setCellValue("C$i", $product["article"]);
    foreach ($product["modifications"] as $modification) {
        $sheet->setCellValue(chr($codeSym++) . $i, (string)$modification["value"]);
        $sheet->getStyle(chr($codeSym) . $i . ':' . chr($codeSym) . $i)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
    }
    $codeSym = $startSymCount;
    $sheet->setCellValue(chr($codeSym++) . "$i", $product["count"]);
    $sheet->setCellValue(chr($codeSym++) . "$i", $product["price"] - $product["discount"]);
    $sheet->setCellValue(chr($codeSym++) . "$i", ($product["price"] - $product["discount"]) * $product["count"]);
    $sheet->setCellValue(chr($codeSym++) . "$i", $product["pricePurchase"]);
    $sheet->setCellValue(chr($codeSym++) . "$i", $product["pricePurchase"] * $product["count"]);
    $i++;
}
foreach (range('A', $endSym) as $columnID)
    $sheet->getColumnDimension($columnID)->setAutoSize(true);

header("Expires: Mon, 1 Apr 1974 05:00:00 GMT");
header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");
header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=order.xlsx");

$objWriter = new PHPExcel_Writer_Excel2007($xls);
$objWriter->save('php://output');
