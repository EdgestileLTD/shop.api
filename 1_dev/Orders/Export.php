<?php

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="orders.zip"');
header('Content-Transfer-Encoding: binary');

// преобразование переменных запроса в переменные БД
function convertFields($str)
{
    $str = str_replace('[id]', 'so.id ', $str);
    $str = str_replace('[idGroup]', 'sp.id_group', $str);
    $str = str_replace('not [isDelete]', '(so.is_delete = "N" OR so.is_delete IS NULL)', $str);
    $str = str_replace('[isDelete]', '(so.is_delete="Y")', $str);
    $str = str_replace('[statusOrder]', 'so.status', $str);
    $str = str_replace('[dateOrder]', 'so.date_order', $str);
    $str = str_replace('[statusDelivery]', 'so.delivery_status', $str);
    $str = str_replace('[idCustomer]', 'p.id ', $str);
    $str = str_replace('[idCoupon]', 'id_coupon', $str);
    return $str;
}

$filter = convertFields($json->filter);
$format = isset($json->format) ? $json->format : "csv";
if ($format == "xml") {
    $dom = new DomDocument('1.0', 'utf-8');
    $rootDOM = $dom->appendChild($dom->createElement('objects'));
}
$root = API_ROOT;
if (IS_EXT)
    $dir = '../app-data/exports/' . $format . '/orders';
else $dir = '../app-data/' . $json->hostname . '/exports/' . $format . '/orders';
if (!file_exists($root . $dir)) {
    $dirs = explode('/', $dir);
    $path = $root;
    foreach ($dirs as $d) {
        $path .= $d;
        if (!file_exists($path))
            mkdir($path, 0700);
        $path .= '/';
    }
}
$dir = $root . $dir;
if (file_exists($dir))
    foreach (glob($dir . '/*') as $file)
        unlink($file);

function createExportFileFromQuery($query, $objectsName, $objectName)
{
    GLOBAL $dir, $xml, $rootXML, $format;

    if ($format == "xml")
        $objectsDOM = $rootXML->appendChild($xml->createElement($objectsName));
    $result = se_db_query($query, 0);
    if ($result) {
        $list = array();
        $header = array();
        while ($row = se_db_fetch_assoc($result)) {
            if (!$header) {
                $header = array_keys($row);
                $list[] = $header;
            }
            if ($format == "csv")
                $list[] = $row;
            if ($format == "xml") {
                $objectDOM = $objectsDOM->appendChild($xml->createElement($objectName));
                for ($i = 0; $i < sizeof($row); $i++) {
                    $item = $objectDOM->appendChild($xml->createElement($header[$i]));
                    $item->appendChild($xml->createTextNode($row[$header[$i]]));
                }
            }
        }

        if ($format == "csv") {
            $fileName = "$objectsName.csv";
            $fileName = $dir . '/' . $fileName;
            $fp = fopen($fileName, 'w');
            foreach ($list as $line) {
                foreach ($line as &$str)
                    $str = iconv('utf-8', 'CP1251', $str);
                fputcsv($fp, $line, ";");
            }
            fclose($fp);
        }
    }
}

// ********************* заказы ************************************************************************************
$u = new seTable('shop_order', 'so');
$u->select('so.id Id, date_order OrderDate, so.manager_id IdManager, id_author IdCustomer,
            CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) Customer,
            p.phone CustomerPhone, p.email CustomerEmail, so.discount OrderDiscount, so.delivery_payee DeliveryCost,
            SUM((sto.price-IFNULL(sto.discount, 0))*sto.count)-IFNULL(so.discount, 0) + IFNULL(so.delivery_payee, 0) OrderAmount,
            so.curr Currency, so.status OrderStatus, delivery_status DeliveryStatus, so.delivery_type DeliveryId,
            delivery_date DeliveryDate, sdt.name DeliveryName, sdt.note DeliveryNote, sd.name_recipient DeliveryNameRecipient,
            sd.telnumber DeliveryPhone, sd.email DeliveryEmail, sd.calltime DeliveryCallTime, sd.address DeliveryAddress,
            sd.postindex DeliveryPostIndex, sd.id_city DeliveryCityId, so.is_delete IsCancelled, so.commentary Note');
$u->leftjoin('person p', 'p.id=so.id_author');
$u->leftjoin('shop_tovarorder sto', 'sto.id_order=so.id');
$u->leftjoin('shop_deliverytype sdt', 'sdt.id=so.delivery_type');
$u->leftjoin('shop_delivery sd', 'sd.id_order=so.id');
if (!empty($filter))
    $u->where($filter);
else $u->where('so.is_delete = "N"');
$u->groupby('so.id');
$u->orderby('so.id');
createExportFileFromQuery($u->getSql(), "orders", "order");

// ********************* товары заказов ****************************************************************************
$u = new seTable('shop_tovarorder', 'st');
$u->select('st.id Id, st.id_order IdOrder, st.id_price IdProduct, st.article ArticleProduct, st.nameitem NameProduct,
            sb.name Brand, st.price Price, st.discount Discount, st.count Count,
            st.modifications IdsModifications, st.commentary Note');
$u->leftjoin('shop_price sp', 'sp.id=st.id_price');
$u->leftjoin('shop_brand sb', 'sb.id = sp.id_brand');
if (!empty($filter)) {
    $u->innerjoin('shop_order so', 'st.id_order=so.id');
    $u->innerjoin('person p', 'p.id=so.id_author');
    $u->where($filter);
}
$u->groupby('st.id');
$u->orderby('st.id');
createExportFileFromQuery($u->getSql(), "orders-products", "order-products");

// ********************* архивирование *****************************************************************************

if ($format == "xml") {
    $fileName = "orders.xml";
    $fileName = $dir . '/' . $fileName;
    $dom->formatOutput = true;
    $dom->save($fileName);
}

// подгружаем библиотеку zip
$zip = new ZipArchive();
$zipName = $dir . '.zip';
if (file_exists($zipName))
    unlink($zipName);
if ($zip->open($zipName, ZIPARCHIVE::CREATE == true)) {
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $zip->addFile($dir . '/' . $file, $file);
            }
        }
        closedir($handle);
    }
}
$zip->close();

echo file_get_contents($zipName);