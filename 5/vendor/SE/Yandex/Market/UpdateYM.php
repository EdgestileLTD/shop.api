<?php

class UpdateYM  extends AYandexMarket {

    public function currency($currencies) {
        foreach ($currencies as $currency) {
            $currency = (string)$currency->attributes()->id;
            $valute_list = $this->getCurrency();
            if (!in_array($currency, $valute_list)) {
                $cbr_code = $this->sbrCode();
                if ($currency == 'RUR' || $currency == 'RUB') {
                    $cbr_code['RUB'] = $cbr_code['RUR'] = array('name' => 'Российский рубль',
                                                                'code' => '');
                }
                $itemCurrency = array('name'    => $currency,
                                      'title'   => $cbr_code[$currency]['name'],
                                      'cbr_kod' => $cbr_code[$currency]['code']);
                $this->setCurrency($itemCurrency);
            }
        }
        $this->save('money_title');
    }

    public function category($groups) {
        foreach ($groups as $group) {
            $id = (int)$group->attributes()->id;
            $parentId = (int)$group->attributes()->parentId;
            $name = (string)$group;

            $categories = $this->getCategory();
            if (!in_array($name, $categories)) {
                $uniqCategory = $this->getTranslitName($name);
                $parentId = (empty($this->categoryTree[$parentId]))
                    ? 'null' : $this->categoryTree[$parentId];
                $itemCategory = array('code_gr' => $uniqCategory,
                                      'name'    => $name,
                                      'upid'    => $parentId,
                                      'visits'  => '00');
                $this->setCategory($itemCategory);
                $ins = $this->save('shop_group');
                $this->categoryTree[$id] = $ins;
            } else {
                $this->categoryTree[$id] = array_search($name, $categories);
            }
        }
    }

    public function offer($offers) {
        // TODO: Implement offer() method.
    }
}