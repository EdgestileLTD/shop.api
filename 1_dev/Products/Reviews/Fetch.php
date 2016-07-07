<?php
// преоброзование переменных запроса в перемнные БД
function convertFields($str) {
    $str = str_replace('[id]', 'sp.id ', $str);
    return $str;
}

$u = new seTable('shop_reviews','sr');
$u->select('sr.*, CONCAT_WS(" ", p.last_name, p.first_name, p.sec_name) name_user, sp.name name_product');
$u->innerjoin('person p','p.id = sr.id_user');
$u->innerjoin('shop_price sp', 'sp.id = sr.id_price');

if (!empty($json->filter))
    $filter = convertFields($json->filter);

$searchStr = $json->searchText;
$searchArr = explode(' ',  $searchStr);

if (!empty($searchStr)) {
    if (strpos($searchStr,'?') === 0) {
        $search = substr($searchStr, 1);
        $search = convertFields($search);
    } else {
        foreach($searchArr as $searchItem) {
            if (!empty($search))
                $search .= " AND ";
            $search .= "(p.last_name like '%$searchItem%'
                            OR p.sec_name like '%$searchItem%'
                            OR p.first_name like '%$searchItem%'
                            OR sr.comment like '%$searchItem%'
                            OR p.email like '%$searchItem%' OR sp.name like '%$searchItem%')";
        }
    }
}

if (!empty($filter))
    $where = $filter;
if (!empty($search)) {
    if (!empty($where))
        $where = "(".$where.") AND (".$search.")";
    else $where = $search;
}

if (!empty($where))
    $u->where($where);
$u->groupby('id');

$patterns = array(
    'id' => 'sr.id',
    'nameProduct' => 'sp.name',
    'nameUser' => 'p.last_name',
    'mark' => 'mark',
    'merits' => 'merits',
    'demerits' => 'demerits',
    'comment' => 'comment',
    'useTime' => 'use_time',
    'dateTime' => 'date',
    'countLikes' => 'likes',
    'countDislikes' => 'dislikes',
    'isActive' => 'active',
    'isActiveIco' => 'active');

$sortBy = (isset($patterns[$json->sortBy])) ? $patterns[$json->sortBy] : 'id';
$u->orderby($sortBy, $json->sortOrder === 'desc');

$count = $u->getListCount();
$result = $u->getList($json->offset, $json->limit);

foreach($result as $item) {
    $review = null;
    $review['id'] = $item['id'];
    $review['idProduct'] = $item['id_price'];
    $review['idUser'] = $item['id_user'];
    $review['nameProduct'] = $item['name_product'];
    $review['nameUser'] = $item['name_user'];
    $review['mark'] = (int) $item['mark'];
    $review['merits'] = $item['merits'];
    $review['demerits'] = $item['demerits'];
    $review['comment'] = $item['comment'];
    $review['useTime'] = (int) $item['use_time'];
    $review['dateTime'] = $item['date'];
    $review['countLikes'] = (int) $item['likes'];
    $review['countDislikes'] = (int) $item['dislikes'];
    $review['isActive'] = (bool) $item['active'];
    $items[] = $review;
}

$data['count'] = $count;
$data['items'] = $items;

$status = array();
if (!se_db_error()) {
    $status['status'] = 'ok';
    $status['data'] = $data;
    $status['sql'] = $view;
} else {
    $status['status'] = 'error';
    $status['errortext'] = se_db_error();
}
outputData($status);