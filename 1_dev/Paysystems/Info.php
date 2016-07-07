<?php

function getParams($idPayment)
{
    $u = new seTable('bank_accounts', 'ba');
    $u->select('ba.*');
    if ($idPayment)
        $u->where('ba.id_payment=?', $idPayment);

    $objects = $u->getList();
    foreach ($objects as $item) {
        $value = null;
        $value['id'] = $item['id'];
        $value['idPayment'] = $item['id_payment'];
        $value['code'] = strtoupper($item['codename']);
        $value['name'] = $item['title'];
        $value['value'] = $item['value'];
        $items[] = $value;
    }
    return $items;
}

function getFilters($idPayment, $articles)
{
    if (empty($articles))
        return null;

    foreach ($articles as $article)
        if ($article) {
            if (!empty($str))
                $str .= ",";
            $str .= "'$article'";
        }
    $u = new seTable('shop_price', 'sp');
    $u->select('`id`, `name`, `article`');
    $u->where("`article` in (?)", $str);
    return $u->getList();
}

function getHosts($hosts)
{
    $result = array();
    foreach ($hosts as $host)
        $result[]['name'] = $host;
    return $result;
}

if (empty($json->ids))
    $json->ids[] = $_GET['id'];
$ids = implode(",", $json->ids);

$u = new seTable('shop_payment', 'sp');
$u->where('sp.id in (?)', $ids);
$result = $u->getList();

$items = array();
foreach ($result as $item) {
    $paySystem = null;
    $paySystem['id'] = $item['id'];
    $paySystem['name'] = $item['name_payment'];
    $paySystem['imageFile'] = $item['logoimg'];
    $paySystem['isExtBlank'] = $item['type'] == 'p';
    $paySystem['isAuthorize'] = $item['authorize'] == 'Y';
    $paySystem['isAdvance'] = $item['way_payment'] == 'b';
    $paySystem['isTestMode'] = $item['is_test'] == 'Y';
    $paySystem['urlHelp'] = $item['url_help'];
    $paySystem['identifier'] = $item['ident'];
    $paySystem['pageSuccess'] = $item['success'];
    $paySystem['pageFail'] = $item['fail'];
    $paySystem['pageBlank'] = $item['blank'];
    $paySystem['pageResult'] = $item['result'];
    $paySystem['pageMainInfo'] = $item['startform'];
    $paySystem['isActive'] = $item['active'] == 'Y';
    $paySystem['sortIndex'] = (int)$item['sort'];
    $paySystem['params'] = getParams($item['id']);
    $paySystem['hosts'] = getHosts(array_filter(explode("\r\n", $item['hosts'])));
    $paySystem['filters'] = getFilters($item['id'], array_filter(explode("\r\n", $item['filters'])));
    if ($paySystem['imageFile']) {
        if (strpos($paySystem['imageFile'], "://") === false) {
            $paySystem['imageUrl'] = 'http://' . $json->hostname . "/images/rus/shoppayment/" . $paySystem['imageFile'];
            $paySystem['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/shoppayment/" . $paySystem['imageFile'];
        } else {
            $paySystem['imageUrl'] = $paySystem['imageFile'];
            $paySystem['imageUrlPreview'] = $paySystem['imageFile'];
        }
    }
    $items[] = $paySystem;

}

$data['count'] = sizeof($items);
$data['items'] = $items;

if (se_db_error()) {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить информация о платежной системе!';
} else {
    $status['status'] = 'ok';
    $status['data'] = $data;
}

outputData($status);
