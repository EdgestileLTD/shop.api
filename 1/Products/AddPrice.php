<?php

class seTableEx extends seTable
{
    function getUpdate()
    {
        return $this->update;
    }

    function getTableName()
    {
        return $this->table_name;
    }
}


function se_db_query_ex($sql, $link = 'db_link')
{
    global $$link;

    return @mysqli_query($$link, $sql);
}

// преоброзование перемнных запроса в перемнные БД
function convertFields($str)
{
    $str = str_replace('idGroup ', 'sg.id ', $str);
    $str = str_replace('[id]', 'sp.id ', $str);
    $str = str_replace('[idGroup]', 'sg.id ', $str);
    $str = str_replace('[idCrossGroup]', 'sgp.group_id ', $str);
    $str = str_replace('[idLinkGroup]', 'scg.id ', $str);
    $str = str_replace('[nameGroup]', 'namegroup ', $str);
    $str = str_replace('[count]', 'presence_count', $str);
    $str = str_replace('[weight]', 'sp.weight', $str);
    $str = str_replace('[volume]', 'sp.volume', $str);
    $str = str_replace('[isNew]=true', 'sp.flag_new="Y"', $str);
    $str = str_replace('[isNew]=false', 'sp.flag_new="N"', $str);
    $str = str_replace('[isHit]=true', 'sp.flag_hit="Y"', $str);
    $str = str_replace('[isHit]=false', 'sp.flag_hit="N"', $str);
    $str = str_replace('[isActive]=true', 'sp.enabled="Y"', $str);
    $str = str_replace('[isActive]=false', 'sp.enabled<>"Y"', $str);
    $str = str_replace('[isDiscount]=true', 'sdl.id>0 AND sp.discount="Y"', $str);
    $str = str_replace('[isDiscount]=false', '(sdl.id IS NULL OR sp.discount="N")', $str);
    $str = str_replace('[isInfinitely]=true', '(sp.presence_count IS NULL OR sp.presence_count<0)', $str);
    $str = str_replace('[isYAMarket]=true', 'sp.is_market=1', $str);
    $str = str_replace('[idBrand]', 'sb.id', $str);
    $str = str_replace('[brand]', 'sb.name', $str);
    $str = str_replace('[idModificationGroup]', 'smg.id', $str);

    return $str;
}

$ids = array();
if (empty($json->ids) && !empty($json->id))
    $ids[] = $json->id;
else $ids = $json->ids;
$idsStr = implode(",", $ids);

if ($ids) {
    $u = new seTableEx('shop_price', 'sp');
    if ($json->value == "a")
        $u->update('price', "price+" . $json->price);
    if ($json->value == "p")
        $u->update('price', "price+price*" . $json->price / 100);
    if (strpos($idsStr, "*") === false) {
        $u->where('id IN (?)', $idsStr);
        $u->save();
    }
    else {
        if (!empty($json->filter)) {
            $filter = convertFields($json->filter);
            $fields = $u->getUpdate();
            $tableName = $u->getTableName();
            $sql = "UPDATE shop_price ";
            $sql .= " LEFT JOIN shop_brand sb ON {$tableName}.id_brand = sb.id";
            if (CORE_VERSION == "5.3") {
                $sql .= " LEFT JOIN shop_price_group spg ON spg.id_price = {$tableName}.id";
                $sql .= " LEFT JOIN shop_group sg ON spg.id_price = sg.id = spg.id_group";
            } else {
                $sql .= " LEFT JOIN shop_group sg ON sg.id = {$tableName}.id_group";
            }
            $sql .= " LEFT JOIN shop_crossgroup scg ON scg.group_id = {$tableName}.id_group";
            $sql .= " LEFT JOIN shop_group_price sgp ON {$tableName}.id = sgp.price_id";
            $sql .= " SET ";
            foreach ($fields as $field => $value)
                $sql .= $tableName . "." . $field . '=' . $value . ',';
            $sql .= $tableName . ".updated_at = '" . date("Y-m-d H:i:s", time()) . "'";
            $sql .= " WHERE {$filter}";
            se_db_query_ex($sql);
        } else {
            $u->where('TRUE');
            $u->save();
        }
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