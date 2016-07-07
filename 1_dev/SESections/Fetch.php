<?php

IF (IS_EXT)
    define('SE_SAFE', $_SERVER['DOCUMENT_ROOT'] . "/");
else define('SE_SAFE', PATH_ROOT . $json->hostname . '/public_html/');
define('SE_DIR', '');

$filePlugin = SE_SAFE . 'lib/plugins/plugin_shop/plugin_shopsections.class.php';
if (file_exists($filePlugin)) {
    require $filePlugin;
    $pluginShopSections = new plugin_shopsections();
    $pluginShopSections->parseProject();
}

$u = new seTable('shop_section', 'ss');
$u->select('ss.id, ss.code');
$u->where('ss.code <> ""');
$rowsSections = $u->getList();

$u = new seTable('shop_section_page', 'ssp');
$u->select('ssp.id, ssp.id_section idSection, ssp.title, ssp.page, ssp.enabled isActive, ssp.se_section');
$u->orderby('se_section');
$rowsPages = $u->getList();

$items = array();
foreach ($rowsSections as $section) {
    $itemParent = null;
    $itemParent['id'] = $section['id'];
    $itemParent['name'] = $section['code'];
    $itemParent['title'] = $section['code'];
    $itemParent['code'] = $section['code'];
    foreach ($rowsPages as $page) {
        if ($page['idSection'] == $section['id']) {
            $item = null;
            $item['id'] = $section['id'] . '_' . $page['id'];
            $item['idParent'] = $section['id'];
            $item['nameGroup'] = $page['se_section'];
            $item['name'] = $page['title'];
            $item['title'] = $page['title'];
            $item['code'] = $section['code'];
            $item['note'] = $page['page'];
            $item['isActive'] = (bool)$page['isActive'];
            if ($itemParent) {
                $items[] = $itemParent;
                $itemParent = null;
            }
            $items[] = $item;
        }
    }
}

$data['count'] = count($items);
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся прочитать разделы!';
}

outputData($status);