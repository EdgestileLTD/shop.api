<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

// интеграция с Яндекс.Маркетом
class Integration extends Base
{
    // возможные типы: B, S, I, D  "(boolean, string, integer, double)"
    private $integrations = '{"integrations" : [
            {"name": "Яндекс.Маркет",
                "parameters": [
                              {"code": "isYAStore", "name": "Наличие точек продаж <store>", "note": "Наличие точки продаж, где товар есть в наличии и его можно купить без предварительного заказа, указывается элементом <store> со значением true. \nЕсли точки продаж нет, используется значение false.",
                              "value": "", "valueSize": 0, "valueType": "B"},
                              {"code": "isPickup", "name": "Возможность самовывоза <pickup>", "note": "Возможность самовывоза описывается элементом <pickup>. \n\nЕсли предусмотрена возможность предварительно заказать данный товар и забрать его в пункте выдачи заказов, используется значение true. \nВ противном случае — false.",
                              "value": "", "valueSize": 0, "valueType": "B"},
                              {"code": "isDelivery", "name": "Возможность доставки товара <delivery>", "note": "Возможность доставки товара обозначается элементом <delivery>. \n\nдоставка товара осуществляется — значение true, \nдоставка товара не осуществляется — значение false. \n\nВнимание! Для таких товаров как ювелирные изделия, лекарственные средства, алкогольная продукция элемент <delivery> должен всегда иметь значение false (доставка не осуществляется).",
                              "value": "1", "valueSize": 0, "valueType": "B"},
                              {"code": "localDeliveryCost", "name": "Местная цена доставки", "note": "В качестве значения можно использовать только целые числа (рубли). Для указания бесплатной доставки используйте значение 0.",
                              "value": "0", "valueSize": 0, "valueType": "I"},
                              {"code": "localDeliveryDays", "name": "Срок доставки (раб. дн.)", "note": "Если магазин готов доставить товары в день заказа (сегодня), используйте значение 0: days=\"0\". Для доставки на следующий день (завтра) используйте значение 1 и т.д. Максимальное значение срока доставки, показываемое на Маркете — 31 день. Если указано значение 32 и больше (либо значение не указано вообще), на Маркете показывается надпись «под заказ». \n\nМожно указать как конкретное количество дней, так и период «от — до». Например, срок доставки от 2 до 4 дней описывается следующим образом: days=\"2-4\". Внимание! При указании периода «от — до» разброс минимального и максимального срока доставки должен составлять не более трех дней. \n\nМаксимально допустимое значение атрибута — 255.",
                              "value": "0", "valueSize": 0, "valueType": "S"},
                              {"code": "salesNotes", "name": "Заметки для продажи <sales_notes>", "note": "Используйте элемент <sales_notes> для указания следующей информации:\n\nминимальная сумма заказа (указание элемента обязательно);\r\nминимальная партия товара (указание элемента обязательно);\r\nнеобходимость предоплаты (указание элемента обязательно);\r\nварианты оплаты (указание элемента необязательно);\r\nусловия акции (указание элемента необязательно).\n\nДопустимая длина текста в элементе — 50 символов.",
                              "value": "", "valueSize": 50, "valueType": "S"},
                              {"code": "exportFeatures", "name": "Выгружать характеристики товаров <param>", "note": "Опция позволяет выгружать характеристики (спецификации) товаров в Яндекс.Маркет. Выгружаться будут только те параметры для которых установлена опция \"Яндекс.Маркет\" на вкладке \"Параметры\", характеристики описываются элементом <param>. Использование элемента <param> является опциональным. Характеристики, переданные через элемент <param>, используются для фильтрации товарных предложений в результатах поиска Маркета. Параметры, переданные через YML, при фильтрации являются более приоритетными по сравнению с автоматически извлеченными данными из элементов <description> и <name>. Названия и значения характеристик, переданных через элемент <param>, служат только для фильтрации товарных предложений и не отображаются в составе товарного предложения. Набор параметров можно составлять самостоятельно, ориентируясь на существующие характеристики товара в фильтре соответствующей категории. Требований к наименованию параметров и их количеству нет.",
                              "value": "", "valueSize": 0, "valueType": "B"},
                              {"code": "exportModifications", "name": "Выгружать модификации товаров <offer>", "note": "Если существует несколько товарных предложений, которые являются вариациями одной модели (модификации), то есть возможность выгружать на Яндекс.Маркет описание каждого варианта в отдельном элементе <offer>. Характеристики для каждой модификации выгружаются с помощью элемента <param>. При переходе с Яндекс.Маркета пользователь попадает в карточку товара с выбранной модификацией на сайте. Для корректного соотнесения всех вариантов с одной моделью на Маркете в описании каждого товарного предложения используется атрибут group_id.",
                              "value": "", "valueSize": 0, "valueType": "B"},
                              {"code": "enabledVendorModel", "name": "Произвольный тип описания <vendor.model>", "note": "В YML существуют несколько типов описаний предложений товаров. Тип устанавливает, какие поля используются для описания предложения. Товарные предложения, описанные не в соответствии со своим типом, могут быть не приняты к публикации. По умолчанию товарные предложения выгружаются в упрощенном типе описания, опция позволяет включить произвольный тип описания. Этот тип описания является наиболее удобным и универсальным, он рекомендован для описания товаров из большинства категорий Яндекс.Маркета. Для данного типа является обязательным указание элементов \"Бренд\" (<vendor>) и \"Модель\" (<model>), тип товара (<typePrefix>) необязательный элемент.",
                              "value": "", "valueSize": 0, "valueType": "B"},
                              {"code": "paramIdForTypePrefix", "name": "Ид. параметра описывающий тип товаров <typePrefix>", "note": "Данный параметр используется, если включена опция \"Произвольный тип описания (vendor.model)\". Требуется указать Ид необходимого параметра, который описывает тип товара. Элемент используется для указания типа / категории товара («мобильный телефон», «стиральная машина», «угловой диван» и т.п.) в произвольном типе описания (vendor.model). Элемент является необязательным, однако его использование существенно влияет на размещение товарного предложения в правильной категории и привязку к карточке товара.\nПри указании типа товара:\n - необходимо руководствоваться тем, как этот товар позиционирует производитель (например, iPad — планшет, а не мобильный телефон);\n - нельзя использовать двусмысленные или слишком общие слова.",
                              "value": "", "valueSize": 50, "valueType": "S"},
                              {"code": "paramIdForModel", "name": "Ид. параметра описывающий модель товара <model>", "note": "Данный параметр используется, если включена опция \"Произвольный тип описания (vendor.model)\". Требуется указать Ид необходимого параметра, который описывает модель товара. Элемент является обязательным для произвольного типа описания. Если параметр не задан, то в качестве значения будет использовано наименование товара.",
                              "value": "", "valueSize": 50, "valueType": "S"}
                              ]
            }
        ]}';

    // получить параметры
    private function getParameters($parameters)
    {
        $u = new DB('shop_integration_parameter');
        $objects = $u->getList();
        foreach ($objects as $item) {
            foreach ($parameters as &$parameter) {
                if ($parameter["code"] == $item["code"]) {
                    $parameter["id"] = $item['id'];
                    $parameter["value"] = $item['value'];
                    break;
                }
            }
        }
        return $parameters;
    }

    // получить
    public function fetch()
    {
        try {
            $items = array();
            $objects = json_decode($this->integrations, 1);
            if ($objects) {
                $objects = $objects["integrations"];
                foreach ($objects as $object) {
                    $integration['name'] = $object["name"];
                    $integration['parameters'] = $this->getParameters($object["parameters"]);
                    $items[] = $integration;
                }
            }
            $this->result["items"] = $items;
            $this->result["count"] = count($items);
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список параметров";
        }
    }

    // сохранить параметры
    private function saveParameters($parameters)
    {
        $idsStr = "";
        foreach ($parameters as $parameter)
            if ($parameter["id"]) {
                if (!empty($idsStr))
                    $idsStr .= ",";
                $idsStr .= $parameter["id"];
            }

        $u = new DB('shop_integration_parameter', 'sip');
        if (!empty($idsStr))
            $u->where("NOT id IN (?)", $idsStr)->deleteList();
        else $u->deleteList();

        $data = array();
        foreach ($parameters as $parameter) {
            if ($parameter["id"] > 0) {
                $u = new DB('shop_integration_parameter', 'sip');
                $u->setValuesFields($parameter);
                $u->save();
            } else $data[] = array("code" => $parameter["code"], "value" => $parameter["value"]);
        }
        if (!empty($data))
            DB::insertList('shop_integration_parameter', $data);
    }

    // сохранить
    public function save()
    {
        try {
            if (isset($this->input["parameters"]))
                $this->saveParameters($this->input["parameters"]);
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить значения параметров!";
        }

    }

}