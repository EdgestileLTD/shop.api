<?php

namespace SE\Yandex\Market;

class InsertYM extends AYandexMarket {

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

            $this->getCategory();  // нужно для транслита
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
        }
    }

    public function offer($offers) {
        $this->getProduct(); // нужно для транслита
        foreach ($offers as $offer) {
            $id = (int)$offer->attributes()->id;
            $groupId = (int)$offer->attributes()->group_id;
            $price = (float)$offer->price;
            $currency = (string)$offer->currencyId;
            $group = (int)$offer->categoryId;
            $type = (string)$offer->attributes()->type;
            $name = ($type == 'vendor.model') ? (string)$offer->model : (string)$offer->name;
            $brand = (string)$offer->vendor;
            $note = (string)$offer->description;
            $url = (string)$offer->url;
            // $rec = (string)$offer->rec;

            if (!empty($brand)) {
                $currentBrand = $this->getBrand();
                if (!in_array($brand, $currentBrand)) {
                    $uniqBrand = $this->getTranslitName($brand);
                    $itemBrand = array('name' => $brand,
                                       'code' => $uniqBrand);

                    $this->setBrand($itemBrand);
                    $brandId = $this->save('shop_brand');
                    $this->addBrand($brandId, $brand);
                } else {
                    $brandId = array_search($brand, $currentBrand);
                }
            }
            if (empty($brandId)) $brandId = 'null';

            $uniqProduct = $this->getTranslitName($name);
            $group = (empty($this->categoryTree[$group]))
                ? 'null' : $this->categoryTree[$group];
            $itemProduct = array('code'           => $uniqProduct,
                                 'lang'           => 'rus',
                                 'id_group'       => $group,
                                 'id_brand'       => $brandId,
                                 'name'           => $name,
                                 'price'          => $price,
                                 'price_opt'      => '00',
                                 'price_opt_corp' => '00',
                                 'bonus'          => '00',
                                 'votes'          => '00',
                                 'note'           => $note,
                                 'curr'           => $currency);
            $this->setProduct($itemProduct);

            foreach ($offer->param as $line) {
                $tmp = (string)$line;
                if (!empty($tmp)) {
                    $this->productParam[$uniqProduct][] = $line;
                }
            }

            foreach ($offer->picture as $line) {
                $tmpPicture = (string)$line;
                $tmpPicture = explode("/", $tmpPicture);
                $tmpPicture = end($tmpPicture);
                $this->pictureNewList[$uniqProduct][] = $tmpPicture;
            }
        }
        $this->save('shop_price');

        $productList = $this->getProductByCode();
        foreach ($this->productParam as $code => $params) {
            foreach ($params as $param) {
                $name = (string)$param->attributes()->name;
                $unit = (string)$param->attributes()->unit;
                $value = (string)$param;

                $currentParam = $this->getParam();
                $idParam = 0;
                if (!in_array($name, $currentParam)) {
                    $unit = (empty($unit)) ? 'null' : $unit;
                    $itemParam = array('name'    => $name,
                                       'measure' => $unit);

                    $this->setParam($itemParam);
                    $idParam = $this->save('shop_feature');
                    $this->addParam($idParam, $name);
                } elseif (in_array($name, $currentParam)) {
                    $idParam = array_search($name, $currentParam);
                }

                if (!empty($idParam)) {
                    $paramValues = $this->getParamValue($idParam);
                    $idValue = 0;
                    if (!in_array($value, $paramValues)) {
                        $itemParamValue = array('id_feature' => $idParam,
                                                'value'      => $value);

                        $this->setParamValue($itemParamValue);
                        $idValue = $this->save('shop_feature_value_list');
                        $tmp[$idValue] = $value;
                        $this->addParamValue($idParam, $tmp);
                    } elseif (in_array($value, $paramValues)) {
                        $idValue = array_search($value, $paramValues);
                    }

                    if (!empty($idValue) && !empty($productList[$code])) {
                        $idPrice = $productList[$code];
                        $itemProductParamValue = array('id_price'   => $idPrice,
                                                       'id_feature' => $idParam,
                                                       'id_value'   => $idValue);
                        $this->setProductParamValue($itemProductParamValue);
                    }
                }
            }
        }
        $this->save('shop_modifications_feature');

        foreach ($this->pictureNewList as $code => $values) {
            foreach ($values as $value) {
                $idPrice = $productList[$code];
                $itemPictureList = array('id_price' => $idPrice,
                                         'picture' => $value);
                $this->setPicture($itemPictureList);
            }
        }

        $this->save('shop_img');
    }
}