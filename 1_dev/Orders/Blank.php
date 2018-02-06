<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/TCPDF/src/tcpdf.php';

function getValue($fields, $id)
{
    foreach ($fields as $field)
        if ($field["idUserField"] == $id)
            return $field["value"];
}

$IS_OUTPUT_DATA = false;

require_once dirname(__FILE__) . '/Info.php';
$order = $status['data']['items'][0];

if (!empty($order["deliveryCityId"])) {
//    $data = array('action' => 'geo', 'idCity' => $order["deliveryCityId"]);
//    $data = http_build_query($data);
//    $url = "https://api.siteedit.ru/api/geo/?" . $data;
//    $curl = curl_init($url);
//    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//    $data = json_decode(curl_exec($curl), true);
//    $city = $data["items"][0]["name"];
//    if (!empty($city))
//        $order[""]
}

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

$pdf->AddFont('times', '', 'times.php');
$pdf->AddFont('timesbd', 'B', 'timesbd.php');
$pdf->AddFont('timesi', 'I', 'timesi.php');
$pdf->AddFont('mistral', '', 'mistral.php');

$pdf->SetFont('times', '', 8);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$y = 0;

$pdf ->Image(PATH_ROOT . $json->hostname . '/public_html/images/logo.jpg', 100, $y + 7.1, 100);

$pdf->SetLineStyle(['dash' => 0, 'width' => 0.3, "color" => [0, 0, 0]]);
$pdf->Line(10, $y + 7, 200, $y + 7);
$pdf->Line(10, $y + 28.9, 200, $y + 28.9);
$pdf->Line(10, $y + 37, 200, $y + 37);
$pdf->Line(100, $y + 7, 100, $y + 37);
$pdf->Line(125, 28.9, 125, 37);
$pdf->Line(10, $y + 49, 200, $y + 49);
$pdf->Line(50, $y + 37, 50, $y + 45);
$pdf->Line(10, $y + 45, 200, $y + 45);

$pdf->SetFont('mistral', '', 18);
$pdf->SetY($y + 10);
$pdf->SetX(25);
$pdf->Cell(20, 6, 'Всё начинается с Цветов');
$pdf->SetFont('times', '', 18);
$pdf->Ln(6);
$pdf->SetX(30);
$pdf->Cell(20, 6, 'flowershop-ku.ru');

$pdf->SetFont('timesbd', '', 12);
$pdf->SetY($y + 28);
$pdf->Cell(20, 10, 'Заказ № ' . $order["id"]);
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y + 28);
$pdf->SetX(100);
$pdf->Cell(20, 6, 'Дата и время');
$pdf->SetY($y + 32);
$pdf->SetX(100);
$pdf->Cell(20, 6, 'доставки');

$pdf->SetFont('times', '', 10);
$dateDelivery = getValue($order["dynFields"], 1);
$dateDelivery = date("d.m.Y", strtotime($dateDelivery));
$pdf->SetY($y + 28);
$pdf->SetX(125);
$pdf->Cell(20, 6, $dateDelivery);

$pdf->SetFont('times', '', 10);
$timeDelivery = getValue($order["dynFields"], 2);
$pdf->SetY($y + 32);
$pdf->SetX(125);
$pdf->Cell(20, 6, $timeDelivery);

$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y + 37);
$pdf->Cell(20, 6, "Адрес доставки");
$pdf->SetFont('times', '', 10);
$pdf->SetX(50);
$pdf->Cell(20, 6, $order["deliveryAddress"]);

$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y + 44);
$pdf->Cell(20, 6, "Состав заказа");

$pdf->SetFont('times', '', 10);
$i = 1;
$y = $y + 50;
$startY = $y;
$pdf->SetY($y);
$imageX = 80;
foreach ($order["items"] as $item) {
    $pdf->Cell(100, 6, $i++ . ". " . $item["originalName"] . " - " . $item["count"] . " " . $item["measurement"]);
    $y = $y + 5;
    $pdf->SetY($y);
    if (!empty($item["imagePath"])) {
        $image = PATH_ROOT . $json->hostname . '/public_html/images/' . $item["imagePath"];
        if (file_exists($image)) {
            $pdf->Image($image, $imageX, $startY - 0.7, 13.7);
            $imageX += 15;
        }
    }
}

if ($y < 62)
    $y = 62;
$y++;
$finishY = $y;
$pdf->SetLineStyle(['dash' => 0, 'width' => 0.3, "color" => [0, 0, 0]]);
$pdf->Line(80, $startY - 1, 80, $y);
$pdf->Line(10, $y, 200, $y);

$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->Cell(20, 6, "Открытка");

$pdf->SetFont('times', '', 9);
$pdf->SetY($y);
$pdf->SetX(50);
$text = getValue($order["dynFields"], 8);
$pdf->MultiCell(148, 6, $text, 0, 'L');

$y = $pdf->GetY();

$pdf->Line(50, $finishY, 50, $y + 30);
$pdf->Line(10, $y, 200, $y);
$pdf->Line(10, $y + 8, 200, $y + 8);
$pdf->Line(10, $y + 20, 200, $y + 20);
$pdf->Line(10, $y + 30, 200, $y + 30);

$pdf->Line(120, $y, 120, $y + 8);
$pdf->Line(145, $y, 145, $y + 8);

$pdf->Line(10, 7, 10, $y + 30);
$pdf->Line(200, 7, 200, $y + 30);


$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->Cell(20, 8, "Получатель");
$pdf->SetFont('times', '', 10);
$customer = getValue($order["dynFields"], 6);
$pdf->SetX(50);
$pdf->Cell(20, 8, $customer);

$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->SetX(120);
$pdf->Cell(20, 4, "Телефон");
$pdf->Ln(4);
$pdf->SetX(120);
$pdf->Cell(20, 4, "получателя");

$pdf->SetFont('times', '', 10);
$phone = getValue($order["dynFields"], 3);
$pdf->SetY($y);
$pdf->SetX(145);
$pdf->Cell(20, 10, $phone);

$y += 10;
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->Cell(20, 8, "Условия доставки");

$pdf->SetFont('times', '', 10);
$termsDelivery = trim(getValue($order["dynFields"], 10));
$pdf->SetX(50);
$pdf->MultiCell(149, 8, $termsDelivery, 0, 'L');

$y += 10;
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->Cell(20, 4, "Отправитель");
$pdf->Ln(4);
$pdf->Cell(20, 4, "(заказчик)");

$pdf->Line(145, $y, 145, $y + 10);
$pdf->Line(120, $y, 120, $y + 10);

$pdf->SetY($y);
$pdf->SetX(120);
$pdf->Cell(20, 4, "Телефон");
$pdf->Ln(4);
$pdf->SetX(120);
$pdf->Cell(20, 4, "отправителя");

$pdf->SetFont('times', '', 10);
$signature = trim(getValue($order["dynFields"], 9));
if (empty($signature))
    $signature = 'Анонимно';
$pdf->SetY($y);
$pdf->SetX(50);
$pdf->Cell(100, 8, $signature);

$pdf->SetX(145);
$pdf->Cell(20, 10, $order["customerPhone"]);

$y += 14;
$pdf->SetLineStyle(['dash' => 0, 'width' => 0.3, "color" => [0, 0, 0]]);
$pdf->Line(10, $y, 200, $y);
$pdf->Line(10, $y + 45, 200, $y + 45);
$pdf->Line(10, $y, 10, $y + 45);
$pdf->Line(200, $y, 200, $y + 45);
$pdf->Line(10, $y + 8, 200, $y + 8);
$pdf->Line(10, $y + 18, 200, $y + 18);
$pdf->Line(10, $y + 31, 200, $y + 31);
$pdf->Line(10, $y + 38, 200, $y + 38);
$pdf->Line(80, $y, 80, $y + 36);
$pdf->Line(50, $y + 36, 50, $y + 45);


$pdf->SetFont('timesbd', '', 10);
$pdf->SetLineStyle(['dash' => 0, 'width' => 0.3, "color" => [0, 0, 0]]);
$pdf->SetY($y);
$pdf->Cell(20, 6, "Подтверждение доставки заказа");
$pdf->SetX(80);
$pdf->Cell(20, 4, "Дата и время доставки");
$pdf->Ln(3.5);
$pdf->SetX(80);
$pdf->Cell(20, 4, "заказа");
$pdf->Line(120, $y, 120, $y + 8);
$pdf->SetFillColor(238, 238, 238);
$pdf->SetY($y);
$pdf->SetX(120);
$pdf->Cell(80, 8, "", 1, 0, 'C', true);

$y += 8;
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->Cell(20, 10, "Адрес доставка, получатель");

$pdf->SetY($y + 0.1);
$pdf->SetX(80);
$pdf->SetFont('times', '', 7);
$pdf->SetTextColor(130, 129, 128);
$pdf->Cell(120, 9.9, "ЕСЛИ ОТЛИЧАЕТСЯ ОТ УКАЗАННОГО ВЫШЕ", 1, 0, 'L', true);

$y += 11;
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(40, 8, "Претензий по качеству и составу");
$pdf->Ln(4);
$pdf->Cell(40, 8, "доставленного не имею");

$pdf->SetY($y - 0.9);
$pdf->SetX(80);
$pdf->SetFont('times', '', 10);
$pdf->SetTextColor(130, 129, 128);
$pdf->Cell(120, 12.9, "ПОДПИСЬ ПОЛУЧАТЕЛЯ", 1, 0, 'L', true);

$y += 12;
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(40, 6, "Флорист");
$pdf->Ln(7);
$pdf->Cell(40, 6, "Курьер");

$pdf->SetY($y + 0.1);
$pdf->SetX(50);
$pdf->SetFont('times', '', 10);
$pdf->SetTextColor(130, 129, 128);
$pdf->Cell(100, 6.9, "ФИО", 1, 0, 'L', true);
$pdf->Cell(50, 6.9, "подпись", 1, 0, 'L', true);

$y += 7;
$pdf->SetY($y + 0.1);
$pdf->SetX(50);
$pdf->SetFont('times', '', 10);
$pdf->SetTextColor(130, 129, 128);
$pdf->Cell(100, 6.9, "ФИО", 1, 0, 'L', true);
$pdf->Cell(50, 6.9, "подпись", 1, 0, 'L', true);

$y += 6;
$pdf->SetLineStyle(['dash' => 4]);
$pdf->Line(10, $y + 5, 200, $y + 5);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetLineStyle(['dash' => 0]);

$y += 1;

$pdf ->Image(PATH_ROOT . $json->hostname . '/public_html/images/logo.jpg', 100, $y + 7.1, 100);

$pdf->SetLineStyle(['dash' => 0, 'width' => 0.3, "color" => [0, 0, 0]]);
$pdf->Line(10, $y + 7, 200, $y + 7);
$pdf->Line(10, $y + 28.9, 200, $y + 28.9);
$pdf->Line(10, $y + 37, 200, $y + 37);
$pdf->Line(100, $y + 7, 100, $y + 37);
$pdf->Line(125, $y + 28.9, 125, $y + 37);
$pdf->Line(50, $y + 37, 50, $y + 45);
$pdf->Line(10, $y + 45, 200, $y + 45);
$pdf->Line(10, $y + 7, 10, $y + 92);
$pdf->Line(200, $y + 7, 200, $y + 92);
$pdf->Line(10, $y + 56, 200, $y + 56);
$pdf->Line(10, $y + 92, 200, $y + 92);

$pdf->SetFont('mistral', '', 18);
$pdf->SetY($y + 10);
$pdf->SetX(25);
$pdf->Cell(20, 6, 'Всё начинается с Цветов');
$pdf->SetFont('times', '', 18);
$pdf->Ln(6);
$pdf->SetX(30);
$pdf->Cell(20, 6, 'flowershop-ku.ru');

$pdf->SetFont('timesbd', '', 12);
$pdf->SetY($y + 28);
$pdf->Cell(20, 10, 'Заказ № ' . $order["id"]);
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y + 28);
$pdf->SetX(100);
$pdf->Cell(20, 6, 'Дата и время');
$pdf->SetY($y + 32);
$pdf->SetX(100);
$pdf->Cell(20, 6, 'доставки');

$pdf->SetFont('times', '', 10);
$dateDelivery = getValue($order["dynFields"], 1);
$dateDelivery = date("d.m.Y", strtotime($dateDelivery));
$pdf->SetY($y + 28);
$pdf->SetX(125);
$pdf->Cell(20, 6, $dateDelivery);

$pdf->SetFont('times', '', 10);
$timeDelivery = getValue($order["dynFields"], 2);
$pdf->SetY($y + 32);
$pdf->SetX(125);
$pdf->Cell(20, 6, $timeDelivery);

$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y + 37);
$pdf->Cell(20, 6, "Адрес доставки");
$pdf->SetFont('times', '', 10);
$pdf->SetX(50);
$pdf->Cell(20, 6, $order["deliveryAddress"]);

$y += 43;
$pdf->SetFont('timesbd', '', 10);
$pdf->SetY($y);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(40, 8, "Претензий по качеству и составу");
$pdf->Ln(4);
$pdf->Cell(40, 8, "доставленного не имею");

$pdf->SetY($y + 2.1);
$pdf->SetX(80);
$pdf->SetFont('times', '', 10);
$pdf->SetTextColor(130, 129, 128);
$pdf->Cell(120, 10.9, "ПОДПИСЬ ПОЛУЧАТЕЛЯ", 1, 0, 'L', true);

$text = "Уход за срезанными цветами:";
$pdf->SetY($y);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('timesbd', '', 8);
$pdf->Cell(200, 30, $text);

$text = "Как только вы принесли цветы домой срежьте конец стебля на каждом цветке. Пусть срез будет косым. Эту процедуру не рекомендуется проводить с помощью ножниц, так как можно раздавить проводящие сосуды, по которым поступает вода. Также следите за тем, чтобы стебли не контактировали с воздухом. Для этого обрезку стебля можно проводить под струей воды.";
$pdf->SetY($y + 17);
$pdf->SetFont('times', '', 8);
$pdf->MultiCell(195, 13, $text, 0, 'L');

$y += 27.5;
$pdf->SetY($y);
$text = 'Листья, которые окажутся ниже уровня воды, необходимо срезать.';
$pdf->Cell(200, 3, $text);

$pdf->Ln();
$text = 'У некоторых видов цветов необходимо удалить пыльники. Это касается лилий и тюльпанов.';
$pdf->Cell(200, 3, $text);

$pdf->Ln();
$text = 'Вазу, в которой стоят цветы, необходимо промывать ежедневно с мылом. Воду из вазы регулярно сливайте и меняйте на свежую.';
$pdf->Cell(200, 3, $text);

$pdf->Ln();
$text = 'При каждой смене воды нужно обновлять срез';
$pdf->Cell(200, 3, $text);

$pdf->Ln();
$text = 'На ночь букеты помещают в прохладное помещение.';
$pdf->Cell(200, 3, $text);

$pdf->Ln();
$text = "Выполняя эти рекомендации цветы будут радовать вас дольше.";
$pdf->SetFont('timesbd', '', 8);
$pdf->Cell(200, 3, $text);


$pdf->Output('report.pdf', 'I');

