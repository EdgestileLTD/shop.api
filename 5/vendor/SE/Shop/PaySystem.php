<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class PaySystem extends Base
{
    protected $tableName = "shop_payment";

    private function getPlugins()
    {
        $urlRoot = 'http://' . HOSTNAME;
        $buffer = file_get_contents($urlRoot . "/lib/merchant/getlist.php");
        $items = explode("|", $buffer);
        $plugins = array();
        foreach ($items as $item)
            if (!empty($item)) {
                $plugin['id'] = $item;
                $plugin['name'] = $item;
                $plugins[] = $plugin;
            }
        return $plugins;
    }

    public function fetch()
    {
        try {
            $u = new DB('shop_payment', 'sp');
            $u->select('sp.id, sp.logoimg, `name_payment`, `active`, `is_test`, `sort`, lang, ident, way_payment');
            $u->orderBy('sort');
            $u->addOrderBy('id');
            $objects = $u->getList($this->limit, $this->offset);
            $paySystems = array();
            foreach ($objects as $item) {
                $paySystem = null;
                $paySystem['id'] = $item['id'];
                $paySystem['imageFile'] = $item['logoimg'];
                $paySystem['identifier'] = $item['ident'];
                $paySystem['name'] = $item['name_payment'];
                $paySystem['isActive'] = $item['active'] == 'Y';
                $paySystem['isTestMode'] = $item['is_test'] == 'Y';
                $paySystem['sortIndex'] = (int)$item['sort'];
                $paySystem['wayPayment'] = $item['way_payment'];
                if ($paySystem['imageFile']) {
                    if (strpos($paySystem['imageFile'], "://") === false) {
                        $paySystem['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shoppayment/" . $paySystem['imageFile'];
                        $paySystem['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shoppayment/" . $paySystem['imageFile'];
                    } else {
                        $paySystem['imageUrl'] = $paySystem['imageFile'];
                        $paySystem['imageUrlPreview'] = $paySystem['imageFile'];
                    }
                }
                $paySystems[] = $paySystem;
            }

            $this->result['count'] = sizeof($objects);
            $this->result['items'] = $paySystems;
            return $paySystems;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список платежных систем!";
        }
    }

    private function getParams()
    {
        $idPayment = $this->input["id"];
        $u = new DB('bank_accounts', 'ba');
        $u->select('ba.*');
        $u->where('ba.id_payment = ?', $idPayment);
        $objects = $u->getList();
        $items = array();
        foreach ($objects as $item) {
            $value = null;
            $value['id'] = $item['id'];
            $value['idPayment'] = $item['id_payment'];
            $value['code'] = strtoupper($item['codename']);
            $value['name'] = $item['title'];
            $value['value'] = $item['value'];
            $items[] = $value;
        }
        return $items;
    }

    private function getFilters($articles)
    {
        if (empty($articles))
            return array();

        foreach ($articles as $article)
            if ($article) {
                if (!empty($str))
                    $str .= ",";
                $str .= "'$article'";
            }
        $u = new DB('shop_price', 'sp');
        $u->select('`id`, `name`, `article`');
        $u->where("`article` IN (?)", $str);
        return $u->getList();
    }

    private function getHosts($hosts)
    {
        $result = array();
        foreach ($hosts as $host)
            $result[]['name'] = $host;
        return $result;
    }


    public function info()
    {
        try {
            if (empty($this->input["id"])) {
                $this->result["plugins"] = $this->getPlugins();
                return;
            }

            $u = new DB('shop_payment', 'sp');
            $paySystem = $u->getInfo($this->input["id"]);
            $paySystem['name'] = $paySystem['name_payment'];
            $paySystem['imageFile'] = $paySystem['logoimg'];
            $paySystem['isExtBlank'] = $paySystem['type'] == 'p';
            $paySystem['isAuthorize'] = $paySystem['authorize'] == 'Y';
            $paySystem['isAdvance'] = $paySystem['way_payment'] == 'b';
            $paySystem['isTestMode'] = $paySystem['is_test'] == 'Y';
            $paySystem['identifier'] = $paySystem['ident'];
            $paySystem['pageSuccess'] = $paySystem['success'];
            $paySystem['pageFail'] = $paySystem['fail'];
            $paySystem['pageBlank'] = $paySystem['blank'];
            $paySystem['pageResult'] = $paySystem['result'];
            $paySystem['pageMainInfo'] = $paySystem['startform'];
            $paySystem['isActive'] = $paySystem['active'] == 'Y';
            $paySystem['sortIndex'] = $paySystem['sort'];
            $paySystem['params'] = $this->getParams();
            $paySystem['hosts'] = $this->getHosts(array_filter(explode("\r\n", $paySystem['hosts'])));
            $paySystem['filters'] = $this->getFilters(array_filter(explode("\r\n", $paySystem['filters'])));
            if ($paySystem['imageFile']) {
                if (strpos($paySystem['imageFile'], "://") === false) {
                    $paySystem['imageUrl'] = 'http://' . HOSTNAME . "/images/rus/shoppayment/" . $paySystem['imageFile'];
                    $paySystem['imageUrlPreview'] = "http://" . HOSTNAME . "/lib/image.php?size=64&img=images/rus/shoppayment/" . $paySystem['imageFile'];
                } else {
                    $paySystem['imageUrl'] = $paySystem['imageFile'];
                    $paySystem['imageUrlPreview'] = $paySystem['imageFile'];
                }
            }
            $this->result = $paySystem;
            return $paySystem;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о платежной системе!";
        }
    }

    public function correctValuesBeforeSave()
    {
        if (isset($this->input["isActive"]))
            $this->input["active"] = $this->input["isActive"] ? "Y" : "N";

        if (empty($this->input["id"]))
            $this->input["sort"] = $this->getSortIndex();
        if (isset($this->input["name"]))
            $this->input["namePayment"] = $this->input["name"];
        if (isset($this->input["identifier"]))
            $this->input["ident"] = $this->input["identifier"];
        if (isset($this->input["isExtBlank"]))
            $this->input["type"] = $this->input["isExtBlank"] ? "p" : "e";
        if (isset($this->input["isAuthorize"]))
            $this->input["authorize"] = $this->input["isAuthorize"] ? "Y" : "N";
        if (isset($this->input["isAdvance"]))
            $this->input["way_payment"] = $this->input["isAdvance"] ? "b" : "a";
        if (isset($this->input["isTestMode"]))
            $this->input["is_test"] = $this->input["isTestMode"] ? "Y" : "N";
        if (isset($this->input["imageFile"]))
            $this->input["logoimg"] = $this->input["imageFile"];
        if (isset($this->input["pageSuccess"]))
            $this->input["success"] = $this->input["pageSuccess"];
        if (isset($this->input["pageFail"]))
            $this->input["fail"] = $this->input["pageFail"];
        if (isset($this->input["pageBlank"]))
            $this->input["blank"] = $this->input["pageBlank"];
        if (isset($this->input["pageResult"]))
            $this->input["result"] = $this->input["pageResult"];
        if (isset($this->input["pageMainInfo"]))
            $this->input["startform"] = $this->input["pageMainInfo"];
        if (isset($this->input["hosts"]))
            $this->input["hosts"] = $this->getHostsStr($this->input["hosts"]);
        if (isset($this->input["filters"]))
            $this->input["filters"] = $this->getFiltersStr($this->input["filters"]);
        if (!empty($this->input["identifier"])) {
            $scriptPlugin = 'http://' . HOSTNAME .
                "/lib/merchant/result.php?payment=" . $this->input["identifier"];
            file_get_contents($scriptPlugin);
        }
    }

    protected function saveAddInfo()
    {
        $this->saveParams();
        return true;
    }

    private function getSortIndex()
    {
        $u = new DB('shop_payment', 'sp');
        $u->select('MAX(`sort`) maxIndex');
        $result = $u->fetchOne();
        return $result["maxIndex"] + 1;
    }

    private function saveParams()
    {
        $params = $this->input["params"];
        $u = new DB('bank_accounts', 'ba');
        $u->where('id_payment = ?', $this->input["id"])->deleteList();

        foreach ($params as $p)
            $data[] = array('id_payment' => $this->input["id"],
                'codename' => $p["code"], 'title' => $p["name"], 'value' => $p["value"]);
        if (!empty($data))
            DB::insertList('bank_accounts', $data);
    }

    private function getHostsStr($hosts)
    {
        $result = "";
        foreach ($hosts as $host) {
            if (!empty($result))
                $result .= "\r\n";
            $result .= $host->name;
        }
        return $result;
    }

    private function getFiltersStr($filters)
    {
        $result = "";
        foreach ($filters as $filter) {
            if (!empty($result))
                $result .= "\r\n";
            $result .= $filter->article;
        }
        return $result;
    }

}