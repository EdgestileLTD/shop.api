<?php

$u = new seTable('main', 'm');
$u->select('m.*');
$u->fetchOne();

function getTokenYandex(&$main) {
    $u = new seTable('integration','i');
    $u->select('io.token, i.is_active, io.login, i.url_api');
    $u->leftjoin('integration_oauth io', 'io.id_integration = i.id');
    $u->fetchOne();
    if ($u->is_active) {
        $main['tokenYandex'] = $u->token;
        $main['loginYandex'] = $u->login;
        $main['apiYandex'] = $u->url_api;

        $_SESSION['tokenYandex'] = $u->token;
        $_SESSION['loginYandex'] = $u->login;
    }
}

if (!isset($u->is_manual_curr_rate) && !$u->isFindField('is_manual_curr_rate')) {
    $u->addField('is_manual_curr_rate', 'TINYINT(1)', 0);
    se_db_query('ALTER TABLE money ADD COLUMN base_currency CHAR(3) NOT NULL DEFAULT "RUB" AFTER kurs;');
    se_db_query('UPDATE money SET base_currency = "' . $u->basecurr . '" WHERE base_currency IS NULL');
}

function getIsShowSESections($hostname)
{
    $pathPages = PATH_ROOT . $hostname . '/public_html/projects/pages.xml';
    if (file_exists($pathPages)) {
        $content = file_get_contents($pathPages);
        return (bool) strpos($content, MODULE_SE_SECTION);
    } else {
        $dat = null;
        if (file_exists($fileDat = PATH_ROOT . $hostname . '/public_html/hostname.dat'))
            $dat = trim(file_get_contents($fileDat));
        $data = explode("\t", $dat);
        $dat  = count($data) == 2 ? $data[1] : $data[0];
        if ($dat) {
            $pathPages = PATH_ROOT . $hostname . "/public_html/projects/{$dat}/pages.xml";
            if (file_exists($pathPages)) {
                $content = file_get_contents($pathPages);
                return (bool)strpos($content, MODULE_SE_SECTION);
            }
        }
    }
    return false;
}

function getIsShowIncPrices()
{
    $result = se_db_fetch_row(se_db_query("SHOW TABLES LIKE 'shop_group_inc_price'"));
    $_SESSION['isIncPrices'] = !empty($result);
    return $_SESSION['isIncPrices'];
}

function getYAMarketCategory($hostname) {
    $pluginMarket = PATH_ROOT . $hostname . '/public_html/lib/plugins/plugin_shop/plugin_yandex_market.class.php';
    if (file_exists($pluginMarket)) {
        require $pluginMarket;
        $plugin = new yandex_market;
        if (method_exists($plugin,'getMarketCategories'))
            return $plugin->getMarketCategories();
    }
}

$status = array();
if ($u->id) {

    $_SESSION['baseCurrency'] = $u->basecurr;
    $_SESSION['language'] = $u->lang;

    $main = null;
    $main['id'] = $u->id;
    $main['nameCompany'] = $u->company;
    $main['shopName'] = $u->shopname;
    $main['subName'] = $u->subname;
    $main['urlLogo'] = $u->logo;
    $main['domain'] = $u->domain;
    $main['baseCurrency'] = $u->basecurr;
    $main['emailSales'] = $u->esales;
    $main['emailSupport'] = $u->esupport;
    $main['head'] = $u->director;
    $main['postHead'] = $u->posthead;
    $main['bookkeeper'] = $u->bookkeeper;
    $main['mailAddress'] = $u->addr_f;
    $main['mainAddress'] = $u->addr_u;
    $main['phone'] = $u->phone;
    $main['fax'] = $u->fax;
    $main['nds'] = (real)$u->nds;
    $main['isManualCurrencyRate'] = (bool)$u->is_manual_curr_rate;
    $main['isYAStore'] = (bool)$u->is_store;
    $main['isYAPickup'] = (bool)$u->is_pickup;
    $main['isYADelivery'] = (bool)$u->is_delivery;
    $main['costYALocalDelivery'] = (real)$u->local_delivery_cost;
    $main['shopFolder'] = $u->folder;
    $main['isShowSESections'] = getIsShowSESections($json->hostname);
    $main['isShowIncPrices'] = getIsShowIncPrices();
    if (!IS_EXT) {
        $main['yaMarketCategories'] = getYAMarketCategory($json->hostname);
        getTokenYandex($main);
    }

    $f = new seTable('money_title', 'm');
    $objects = $f->getList();
    foreach ($objects as $item) {
        $curr = null;
        $curr['id'] = $item['id'];
        $curr['code'] = $item['name'];
        $curr['name'] = $item['title'];
        $curr['cbrCode'] = $item['cbr_kod'];
        $curr['prefix'] = $item['name_front'];
        $curr['suffix'] = $item['name_flang'];
        $curr['isCanAutoRate'] = true;

        if ($main['baseCurrency'] === $curr['code']) {
            $main['baseCurrencyPrefix'] = $curr['prefix'];
            $main['baseCurrencySuffix'] = $curr['suffix'];
        }
        $main['listCurrency'][] = $curr;
    }
    $main["apiBuild"] = API_BUILD;
    $items[] = $main;
}
$data['count'] = sizeof($items);
$data['items'] = $items;

if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = 'Не удаётся получить информацию о компании!';
}
outputData($status);