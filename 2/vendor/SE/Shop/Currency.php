<?php

namespace SE\Shop;

use SE\DB as seTable;
use SE\Exception;

IF (IS_EXT)
    $dirRoot = $_SERVER['DOCUMENT_ROOT'];
else $dirRoot = $_SERVER['DOCUMENT_ROOT'] . '/api';
$dirLib = $dirRoot . "/lib";
$scriptCurrency = $dirLib . "/lib_currency.php";
if ($isCurrLib = file_exists($scriptCurrency))
    require_once $scriptCurrency;
define("IS_CURR_LIB", $isCurrLib);

// валюты
class Currency extends Base
{
    protected $tableName = "money_title";
    private $baseCurr = null;

    // формат валюты
    private function formatCurrency($nameFront, $nameFlank, $value)
    {
        if ($nameFront)
            return $nameFront . ' ' . $value;
        return $value . ' ' . $nameFlank;
    }

    /* ПОЛУЧИТЬ СПИСОК ВАЛЮТ
     * отправляет нумерованный массив валют со значениями:
     *      id lang name title nameFront nameFlang cbrKod minsum updatedAt createdAt
     * $this->result используется в валюте товара
     */
    public function fetch()
    {
        try {
            $isRate = isset($this->input["rateDate"]) && !empty($this->input["rateDate"]);

            $u = new seTable('main', 'm');
            $u->select('m.is_manual_curr_rate, m.basecurr, mt.*');
            $u->innerJoin('money_title mt', 'm.basecurr = mt.name');;
            $base = $u->fetchOne();
            $isManualMode = (bool)$base['isManualCurrRate'];
            $baseNameFront = $base['nameFront'];
            $baseNameFlank = $base['nameFlang'];
            $baseCurrency = empty($this->baseCurr) ? $base['basecurr'] : $this->baseCurr;

            if (!$isRate)
                $this->input["dateReplace"] = date('Y-m-d');
            $u = new seTable('money_title', 'mt');
            $u->select('mt.*');
            $objects = $u->getList();
            $rates = array();
            if ($isManualMode) {
                $m = new seTable('money', 'm');
                $m->select('m.*');
                $m->where("m.base_currency = '$baseCurrency' AND m.date_replace <= '{$this->input["dateReplace"]}'");
                $m->orderBy('m. date_replace', 1);
                $rates = $m->getList();
            }

            $count = 0;
            foreach ($objects as $item) {
                $count++;
                $curr = $item;
                if ($curr['name'] == $baseCurrency)
                    $curr['rate'] = 1;
                else {
                    $curr['rate'] = (float)$item['kurs'];
                    if (!$isManualMode && IS_CURR_LIB) {
                        $baseValues = getCurrencyValues($baseCurrency);
                        $currencyValues = getCurrencyValues($curr['name']);
                        $baserate = (float)str_replace(",", ".", $baseValues['Value']) / str_replace(",", ".", $baseValues['Nominal']);
                        if (empty($baserate)) $baserate = 1;
                        $currrate = (float)str_replace(",", ".", $currencyValues['Value']) / str_replace(",", ".", $currencyValues['Nominal']);
                        if (empty($currrate)) $currrate = 1;
                        if ($baserate > 0)
                            $curr['rate'] = ($currrate / $baserate);
                        else $curr['rate'] = null;
                        if ($curr['rate'] < 0)
                            $curr['rate'] = null;
                        $curr['dateReplace'] = date('Y-m-d');
                    } else {
                        foreach ($rates as $rate) {
                            if ($rate['name'] == $curr['name']) {
                                if (!empty($rate['kurs']))
                                    $curr['rate'] = (float)$rate['kurs'];
                                break;
                            }
                        }
                    }
                }

                $curr['rateDisplay'] = $this->formatCurrency($curr['nameFront'], $curr['nameFlang'], 1) . ' = ' .
                    $this->formatCurrency($baseNameFront, $baseNameFlank, $curr['rate']);
                $items[] = $curr;
            }

            $data['count'] = $count;
            $data['items'] = $items;
            $this->result = $data;
            return $objects;
        } catch (Exception $e) {
            $this->result = "Не удаётся получить список валют!";
        }
    }

    public function convert() {

        $this->baseCurr = $this->input["target"];
        $source = $this->input["source"];
        $price = $this->input["price"];


        $this->fetch();
        $currencies = $this->result["items"];


        foreach ($currencies as $currency) {
            if (strtolower($currency["name"]) == strtolower($source)) {
                $this->result["price"] = round($price * $currency["rate"], 2);
                break;
            }
        }

    }

}
