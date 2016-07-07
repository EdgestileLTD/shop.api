<?php
    // преоброзование перемнных запроса в перемнные БД
    function convertFields($str) {
        $str = str_replace('id ', 'sc.id ', $str);
        $str = str_replace('count', 'count_used', $str);
        $str = str_replace('timeEnd', 'expire_date', $str);
        $str = str_replace('minSum', 'min_sum_order', $str);
        $str = str_replace('isActive', 'status', $str);
        return $str;
    }

    $u = new seTable('shop_coupons','sc');
    $u->select('sc.*');

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
                $search .= "(sc.code LIKE '%$searchItem%'
                        OR sc.discount = '$searchItem')";
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

    $json->sortBy = convertFields($json->sortBy);

    if ($json->sortBy)
        $u->orderby($json->sortBy, $json->sortOrder === 'desc');
    $view = $u->getsql();

    $objects = $u->getList();
    foreach($objects as $item) {
        $coupon = null;
        $coupon['id'] = $item['id'];
        $coupon['code'] = $item['code'];
        $coupon['type'] = $item['type'];
        $coupon['discount'] = (real) $item['discount'];
        $coupon['currencyCode'] = $item['currency'];
        $coupon['timeEnd'] = $item['expire_date'];
        $coupon['minSum'] = (float) $item['min_sum_order'];
        $coupon['isActive'] = (bool) ($item['status'] == "Y");
        $coupon['count'] = (int) $item['count_used'];
        $coupon['isRegUser'] = (bool) ($item['only_registered'] == "Y");
        $items[] = $coupon;
    }

    $data['count'] = sizeof($items);
    $data['items'] = $items;

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['errortext'] = se_db_error();
    }
    outputData($status);