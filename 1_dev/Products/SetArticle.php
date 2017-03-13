<?php
    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;

    function getArticle()
    {
        $u = new seTable('shop_price','sp');
        $u->select('MAX(`article` + 1) AS art');
        $result = $u->fetchOne();
        $result = (!empty($result['art'])) ? $result['art'] : '1';
        $l = strlen($result);
        if ($l < 12)
            for ($i = 0; $i < (12 - $l); ++$i)
                $result = "0" . $result;
        return $result;
    }

    if ($ids) {
        $u = new seTable('shop_price','sp');
        $u->select('id, name');
        $idsStr = implode(",", $ids);
        if ($idsStr != "all")
            $u->where("id in (?)", $idsStr);
        $objects = $u->getList();
        foreach($objects as $item) {
            $u = new seTable('shop_price','sp');
            $article = getArticle();
            $u->update('article', "'$article'");
            $u->where('id=?', $item['id']);
            $u->save();
        }
    }

    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $ids;
    } else {
        $status['status'] = 'error';
        $status['error'] = se_db_error();
    }

    outputData($status);