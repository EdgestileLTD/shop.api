<?php
require_once dirname(__FILE__) . '/Payments/functions.php';

$id = $_GET['id'];
if (!$id) {
    if (empty($json->ids))
        exit;

    if (sizeof($json->ids))
        $id = $json->ids[0];
    else exit;
} else $json->ids[] = $id;

$ids = implode(",", $json->ids);
$url_img = 'http://' . $json->hostname . '/images/';


function getPaidSum($idOrder)
{
    correctTablePayee();
    $u = new seTable('shop_order_payee', 'sop');
    $u->select('SUM(amount) amount');
    $u->where("sop.id_order = (?)", $idOrder);
    $result = $u->getList();
    unset($u);

    if (!empty($result))
        foreach ($result as $item)
            return (real)$item['amount'];

    return 0;
}

function getPayments($idOrder)
{
    $u = new seTable('shop_order_payee', 'sop');
    $u->select('sop.*, (SELECT name_payment FROM shop_payment WHERE id=sop.payment_type) as name,
                        CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) as payer, sua.in_payee');
    $u->innerjoin('person p', 'p.id=sop.id_author');
    $u->leftjoin('se_user_account sua', 'sua.id=sop.id_user_account_out');
    $u->where("sop.id_order = ?", $idOrder);
    $u->groupby('sop.id');
    $objects = $u->getList();
    $result = array();
    foreach ($objects as $item) {
        $payment = array();
        $payment['id'] = $item['id'];
        $payment['name'] = $item['name'];
        if (empty($payment['name']))
            $payment['name'] = 'С лицевого счета';
        $payment['idOrder'] = $item['id_order'];
        $payment['idPayer'] = $item['id_author'];
        $payment['payerName'] = $item['payer'];
        $payment['paymentTarget'] = (int)$item['payment_target'];
        $payment['idPaymentType'] = $item['payment_type'];
        $payment['idManager'] = $item['id_manager'];
        $payment['docNum'] = $item['num'];
        $payment['docDate'] = date('Y-m-d', strtotime($item['date']));
        $payment['docYear'] = (int)$item['year'];
        $payment['orderAmount'] = (real)$item['in_payee'];
        $payment['amount'] = (real)$item['amount'];
        $payment['note'] = $item['note'];
        $payment['idUserAccountIn'] = $item['id_user_account_in'];
        $payment['idUserAccountOut'] = $item['id_user_account_out'];
        $result[] = $payment;
    }
    return $result;
}

function getModifications($item)
{
    $u = new seTable('shop_modifications_feature', 'smf');
    $u->select('sf.name, sfl.value');
    $u->innerjoin('shop_feature sf', 'sf.id=smf.id_feature');
    $u->innerjoin('shop_feature_value_list sfl', 'sfl.id=smf.id_value');
    $u->where('smf.id_modification in (?)', $item['modifications']);

    $result = $u->getList();
    if (!$result && $item['modifications']) {
        $name = substr($item['nameitem'], strpos($item['nameitem'], '(') + 1, strpos($item['nameitem'], ')') - strpos($item['nameitem'], '(') - 1);
        if ($name) {
            $items = explode(", ", $name);
            foreach ($items as $item) {
                $nameItem = substr($item, 0, strpos($item, ':'));
                if ($nameItem) {
                    $mod["name"] = $nameItem;
                    $mod["value"] = substr($item, strpos($item, ':') + 2);
                    $result[] = $mod;
                }
            }
        }
    }
    return $result;
}


function getOptions($idProduct, $idItem)
{
    if (!$_SESSION["isShowOptions"])
        return null;

    $options = array();

    $u = new seTable('shop_product_option', 'spo');
    $u->select('so.id id_option, so.name `option`, so.type, so.type_price, sto.id id_tovarorder_option,  
                sov.id, sov.name, spo.price, spo.is_default, so.is_counted');
    $u->innerJoin('shop_option_value sov', 'spo.id_option_value = sov.id');
    $u->innerJoin('shop_option so', 'sov.id_option = so.id');
    $u->leftJoin('shop_tovarorder_option sto', "sto.id_option_value = spo.id_option_value AND sto.id_tovarorder = {$idItem}");
    $u->where('spo.id_product = ?', $idProduct);
    $u->orderBy('so.sort');
    $u->addOrderBy('sov.sort');
    $u->groupBy("spo.id");

    $objects = $u->getList();
    $listOptions = array();
    foreach ($objects as $object) {
        $value = null;
        $value["id"] = $object["id"];
        $value["name"] = $object["name"];
        $value["price"] = (float)$object["price"];
        $value["isDefault"] = (bool)$object["is_default"];
        $value["isChecked"] = !empty($object["id_tovarorder_option"]);

        $listOptions[$object["id_option"]]["id"] = $object["id_option"];
        $listOptions[$object["id_option"]]["name"] = $object["option"];
        $listOptions[$object["id_option"]]["isCounted"] = $object["is_counted"];
        $listOptions[$object["id_option"]]["type"] = (int)$object["type"];
        $listOptions[$object["id_option"]]["typePrice"] = (int)$object["type_price"];
        $listOptions[$object["id_option"]]["optionValues"][] = $value;
    }

    foreach ($listOptions as $option)
        $options[] = $option;

    return $options;
}

function getOrderItems($idOrder, $currency)
{
    global $url_img;

    $select = "sto.*, sp.code, sp.id_group, sp.curr, sp.lang, sp.img, si.picture, sp.measure, sp.name price_name";
    if ($_SESSION["isShowOptions"])
        $select .= ", COUNT(sop.id) count_options";

    $u = new seTable('shop_tovarorder', 'sto');
    $u->select($select);
    $u->leftjoin('shop_price sp', 'sp.id=sto.id_price');
    $u->leftjoin('shop_img si', 'si.id_price=sto.id_price AND si.`default`=1');
    if ($_SESSION["isShowOptions"])
        $u->leftjoin('shop_tovarorder_option sop', 'sto.id = sop.id_tovarorder');
    $u->where("id_order=?", $idOrder);
    $u->groupby('sto.id');
    $result = $u->getList();
    unset($u);
    $items = array();
    if (!empty($result)) {
        foreach ($result as $item) {
            if ($item['picture']) $item['img'] = $item['picture'];
            $product['id'] = $item['id'];
            $product['idPrice'] = $item['id_price'];
            $product['code'] = $item['code'];
            $product['name'] = $item['nameitem'];
            $product['originalName'] = $item['price_name'];
            $product['idsModifications'] = $item['modifications'];
            $product['modifications'] = getModifications($item);
            $product['article'] = $item['article'];
            $product['measurement'] = $item['measure'];
            $product['idGroup'] = $item['id_group'];
            $product['price'] = (real)$item['price'];
            $product['pricePurchase'] = (real)$item['price_purchase'];
            $product['count'] = (real)$item['count'];
            $product['countOptions'] = (int)$item['count_options'];
            $product['bonus'] = (real)$item['bonus'];
            $product['discount'] = (real)$item['discount'];
            $product['currency'] = $currency;
            $product['license'] = $item['license'];
            $product['note'] = $item['commentary'];
            $product['imagePath'] = 'rus/shopprice/' . $item['img'];
            $product['imageFile'] = (strpos($item['img'], '://') === false) ? $url_img . $item['lang'] . '/shopprice/' . $item['img'] : $item['img'];
            if ($_SESSION["isShowOptions"]) {
                $product['options'] = getOptions($product['idPrice'], $item["id"]);
            }
            $items[] = $product;
        }
    }
    return $items;
}

function getDynFields($idOrder)
{
    $u = new seTable('shop_userfields', 'su');
    $u->select("sou.*, su.id idMain, su.type, sou.id_userfield");
    $u->leftjoin('shop_order_userfields sou', "(sou.id_userfield = su.id AND id_order = {$idOrder}) OR id_order IS NULL");
    $u->groupby('su.id');
    $u->orderby('su.sort');
    $result = $u->getList();
    $items = array();
    foreach ($result as $item) {
        $field['id'] = $item['id'];
        $field['idMain'] = $item['idMain'];
        $field['value'] = $item['value'];
        $field['idUserField'] = $item['id_userfield'];
        if ($item['type'] == "date")
            $field['value'] = date('Y-m-d', strtotime($item['value']));
        $items[] = $field;
    }
    return $items;
}


$u = new seTable('shop_delivery');
if (!$u->isFindField('id_city')) {
    $u->addField('id_city', 'int(10) default NULL', 1);
}
unset($u);


$select = 'so.*, sto.nameitem, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) as customer, p.phone as customerPhone,
                p.email as customerEmail, 
                c.name company, c.phone companyPhone, c.email companyEmail,
                (SUM((sto.price-IFNULL(sto.discount, 0))*sto.count)-IFNULL(so.discount, 0)+IFNULL(so.delivery_payee, 0)) as summ,
                sdt.name as delivery_name, sdt.note AS delivery_note,
                sd.id_city,sd.name_recipient, sd.telnumber, sd.email, sd.calltime, sd.address, sd.postindex,
                CONCAT_WS(" ",  pm.last_name, pm.first_name, pm.sec_name) as manager, sp.name_payment,
                sdts.note delivery_note_add';
$u = new seTable('shop_order', 'so');
$u->select($select);
$u->leftjoin('person p', 'p.id = so.id_author');
$u->leftjoin('company c', 'c.id=so.id_company');
$u->leftjoin('person pm', 'pm.id = so.id_admin');
$u->innerjoin('shop_tovarorder sto', 'sto.id_order = so.id');
$u->leftjoin('shop_deliverytype sdt', 'sdt.id = so.delivery_type');
$u->leftjoin('shop_delivery sd', 'sd.id_order = so.id');
$u->leftjoin('shop_deliverytype sdts', 'sdts.id = sd.id_subdelivery');
$u->leftjoin('shop_payment sp', 'sp.id = so.payment_type');
if (!empty($ids))
    $u->where("so.id IN (?)", $ids);
else $u->where("so.id IS NULL");
$u->groupby('so.id');

$result = $u->getList();
unset($u);

$items = array();
if (!empty($result)) {
    foreach ($result as $item) {
        $order = null;
        $order['id'] = $item['id'];
        $order['isCanceled'] = $item['is_delete'] == 'Y';
        $order['dateOrder'] = $item['date_order'];
        $order['datePayee'] = $item['date_payee'];
        $order['dateCredit'] = date('Y-m-d', strtotime($item['date_credit']));
        $order['idCustomer'] = $item['id_author'];
        $order['idCompany'] = $item['id_company'];
        $order['customer'] = $item['company'] ? $item['company'] : $item['customer'];
        $order['idManager'] = $item['id_admin'];
        $order['managers'] = $item['managers'];
        $order['currency'] = $item['curr'];
        $order['customerPhone'] = $item['companyPhone'] ? $item['companyPhone'] : $item['customerPhone'];
        $order['customerEmail'] = $item['companyEmail'] ? $item['companyEmail'] : $item['customerEmail'];
        $order['statusOrder'] = $item['status'];
        $order['sum'] = (real)$item['summ'];
        $order['discountSum'] = (real)$item['discount'];
        $order['deliverySum'] = (real)$item['delivery_payee'];
        $order['note'] = htmlspecialchars_decode($item['commentary']);
        $order['countOptions'] = (int)$item['count_options'];

        // информация о доставке
        $order['statusDelivery'] = $item['delivery_status'];
        $order['deliveryId'] = $item['delivery_type'];
        $order['deliveryName'] = $item['delivery_name'];
        $order['deliveryNote'] = $item['delivery_note'];
        if (!empty($item['delivery_note_add']))
            $order['deliveryNote'] = $item['delivery_note_add'];
        $order['deliveryDate'] = $item['delivery_date'];

        $order['deliveryNameRecipient'] = $item['name_recipient'];
        $order['deliveryPhone'] = $item['telnumber'];
        $order['deliveryEmail'] = $item['email'];
        $order['deliveryCallTime'] = $item['calltime'];
        $order['deliveryAddress'] = $item['address'];
        $order['deliveryPostIndex'] = $item['postindex'];
        $order['deliveryCityId'] = $item['id_city'];

        $order['deliveryDocNum'] = $item['delivery_doc_num'];
        $order['deliveryDocDate'] = $item['delivery_doc_date'];
        $order['deliveryServiceName'] = $item['delivery_service_name'];

        // информация об оплате
        $order['payeeDoc'] = $item['payee_doc'];
        $order['account'] = $item['account'];
        $order['transactSum'] = (real)$item['transact_amount'];
        $order['transactId'] = $item['transact_id'];
        $order['transactCurrency'] = $item['transact_curr'];
        $order['paymentTypePrimary'] = $item['name_payment'];

        // список товаров
        $order['items'] = getOrderItems($order['id'], $order['currency']);
        // сумма оплат по заказу
        $order['paidSum'] = getPaidSum($order['id']);
        // счета
        $order['payments'] = getPayments($order['id']);
        // доп. поля заказа
        $order['dynFields'] = getDynFields($order['id']);
        $items[] = $order;
    }
}

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = array('count' => count($items), 'items' => $items);
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о заказе!';
}

outputData($status);
