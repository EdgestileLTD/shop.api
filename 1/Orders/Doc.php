<?php

$IS_OUTPUT_DATA = false;

require_once dirname(__FILE__) . '/Info.php';
$order = $status['data']['items'][0];

if (IS_EXT) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/dompdf/dompdf_config.inc.php';
} else {
    require_once '/home/e/edgestile/admin/home/siteedit/lib/classes/seCurrency.class.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_utf8.php';
    require_once '/home/e/edgestile/admin/home/siteedit/lib/lib_se_function.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/dompdf/dompdf_config.inc.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api/lib/plugin_macros.class.php';
}

$u = new seTable('shop_payment', 'sp');
$u->select("blank");
$u->where("sp.name_payment LIKE '%наличный%'");
$u->fetchOne();
$blank = $u->blank;
if ($blank) {
    $macros = new plugin_macros(0, $order["id"]);
    $blank = $macros->execute($blank);
    $blank = str_replace("&nbsp;", " ", $blank);
    $html = '<!DOCTYPE html><html style="font-family: ' . "'Arial Unicode MS'" . '"><meta http-equiv="content-type" content="text/html; charset=UTF-8" /><body>' . $blank . '</body></html>';
    $dompdf = new DOMPDF();
    $dompdf->load_html($html);
    $dompdf->render();
    echo $dompdf->output(0);
    //echo $html;
}
