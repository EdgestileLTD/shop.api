<?php
// преоброзование перемнных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('id ', 'n.id ', $str);
    $str = str_replace('idGroup', 'n.id_category', $str);
    $str = str_replace('newsDate', 'n.news_date', $str);
    $str = str_replace('image', 'n.id', $str);
    $str = str_replace('name', 'n.title', $str);
    $str = str_replace('display', 'n.title', $str);
    return $str;
}

$u = new seTable('news', 'n');
$u->select('n.*');

if (!empty($json->filter))
    $filter = convertFields($json->filter);
if (!empty($filter))
    $where = $filter;
if (!empty($where))
    $where = "(" . $where . ")";
if (!empty($where))
    $u->where($where);
$u->groupby('id');

$json->sortBy = convertFields($json->sortBy);
if ($json->sortBy)
    $u->orderby($json->sortBy, $json->sortOrder === 'desc');

$count = $u->getListCount();
$objects = $u->getList($json->offset, $json->limit);
foreach ($objects as $item) {
    $new = null;
    $new['id'] = $item['id'];
    $new['idGroup'] = $item['id_category'];
    $new['name'] = $item['title'];
    $new['isActive'] = $item['active'] == 'Y';
    $new['imageFile'] = $item['img'];
    $new['fullDescription'] = $item['text'];
    if (!empty($item['news_date']))
        $new['newsDate'] = date('Y-m-d', $item['news_date']);
    if (!empty($item['pub_date'])) {
        $new['publicationDate'] = date('Y-m-d', $item['pub_date']);
        $new['publicationDateDisplay'] = date('d.m.Y', $item['pub_date']);
    }
    if ($new['imageFile']) {
        if (strpos($new['imageFile'], "://") === false) {
            $new['imageUrl'] = 'http://' . $json->hostname . "/images/rus/newsimg/" . $new['imageFile'];
            $new['imageUrlPreview'] = "http://{$json->hostname}/lib/image.php?size=64&img=images/rus/newsimg/" . $new['imageFile'];
        } else {
            $new['imageUrl'] = $new['imageFile'];
            $new['imageUrlPreview'] = $new['imageFile'];
        }
    }
    $items[] = $new;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['errortext'] = 'Не удаётся получить список новостей!';
}

outputData($status);