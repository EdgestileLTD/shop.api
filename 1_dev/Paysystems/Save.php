<?php

    function getSortIndex() {
        $u = new seTable('shop_payment', 'sp');
        $u->select('MAX(`sort`) as maxIndex');
        $u->fetchOne();
        return $u->maxIndex + 1;
    }

    function saveParams($ids, $params) {
        $idsStr = implode(",", $ids);
        $u = new seTable('bank_accounts','ba');
        $u->where('id_payment in (?)', $idsStr)->deletelist();

        $u = new seTable('bank_accounts', 'ba');
        foreach ($params as $p)
            foreach($ids as $idPay)
                $data[] = array('id_payment' => $idPay, 'codename' => $p->code, 'title' => $p->name, 'value' => $p->value);
        if (!empty($data))
            se_db_InsertList('bank_accounts', $data);
    }

    function getHostsStr($hosts) {
        $result = "";
        foreach($hosts as $host) {
            if (!empty($result))
                $result .= "\r\n";
            $result .= $host->name;
        }
        return $result;
    }

    function getFiltersStr($filters) {
        $result = "";
        foreach($filters as $filter) {
            if (!empty($result))
                $result .= "\r\n";
            $result .= $filter->article;
        }
        return $result;
    }

    $ids = array();
    if (empty($json->ids) && !empty($json->id))
        $ids[] = $json->id;
    else $ids = $json->ids;
    $isNew = empty($ids);
    if (!$isNew)
        $idsStr = implode(",", $ids);

    $u = new seTable('shop_payment', 'sp');

    if ($isNew || !empty($ids)) {
        $isUpdated = false;
        if (isset($json->isActive)) {
            if ($json->isActive)
                $isUpdated |= setField($isNew, $u, 'Y', 'active');
            else $isUpdated |= setField($isNew, $u, 'N', 'active');
        }
        if ($isNew)
            $isUpdated |= setField($isNew, $u, getSortIndex(), 'sort');
        $isUpdated |= setField($isNew, $u, $json->name, 'name_payment');
        $isUpdated |= setField($isNew, $u, $json->identifier, 'ident');
        if (isset($json->isExtBlank)) {
            if ($json->isExtBlank)
                $isUpdated |= setField($isNew, $u, 'p', 'type');
            else $isUpdated |= setField($isNew, $u, 'e', 'type');
        }
        if (isset($json->isAuthorize)) {
            if ($json->isAuthorize)
                $isUpdated |= setField($isNew, $u, 'Y', 'authorize');
            else $isUpdated |= setField($isNew, $u, 'N', 'authorize');
        }
        if (isset($json->isAdvance)) {
            if ($json->isAdvance)
                $isUpdated |= setField($isNew, $u, 'b', 'way_payment');
            else $isUpdated |= setField($isNew, $u, 'a', 'way_payment');
        }
        if (isset($json->isTestMode)) {
            if ($json->isTestMode)
                $isUpdated |= setField($isNew, $u, 'Y', 'is_test');
            else $isUpdated |= setField($isNew, $u, 'N', 'is_test');
        }
        $isUpdated |= setField($isNew, $u, $json->imageFile, 'logoimg');
        $isUpdated |= setField($isNew, $u, $json->urlHelp, 'url_help');
        $isUpdated |= setField($isNew, $u, $json->pageSuccess, 'success');
        $isUpdated |= setField($isNew, $u, $json->pageFail, 'fail');
        $isUpdated |= setField($isNew, $u, $json->pageBlank, 'blank');
        $isUpdated |= setField($isNew, $u, $json->pageResult, 'result');
        $isUpdated |= setField($isNew, $u, $json->pageMainInfo, 'startform');
        $isUpdated |= setField($isNew, $u, $json->customerType, 'customer_type');
        if (isset($json->hosts))
            $isUpdated |= setField($isNew, $u, getHostsStr($json->hosts), 'hosts');
        if (isset($json->filters))
            $isUpdated |= setField($isNew, $u, getFiltersStr($json->filters), 'filters');
        if ($isUpdated){
            if (!empty($idsStr)) {
                if ($idsStr != "all")
                    $u->where('id in (?)', $idsStr);
                else $u->where('true');
            }
            $idv = $u->save();
            if ($isNew)
                $ids[] = $idv;

            if (!empty($json->identifier)) {
                $scriptPlugin = 'http://' . $json->hostname .
                    "/lib/merchant/result.php?payment=" . $json->identifier;
                file_get_contents($scriptPlugin);
            }
        }
        if ($ids && isset($json->params))
            saveParams($ids, $json->params);
    }

    $data['id'] = $ids[0];
    $status = array();
    if (!se_db_error()) {
        $status['status'] = 'ok';
        $status['data'] = $data;
    } else {
        $status['status'] = 'error';
        $status['error'] = 'Не удаётся сохранить настройки платежной системы!';
    }

    outputData($status);