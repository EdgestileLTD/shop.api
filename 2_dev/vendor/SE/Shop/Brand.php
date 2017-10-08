<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Brand extends Base
{
    protected $tableName = "shop_brand";

    // получить
    public function fetch($isId = false)
    {
        try {
            $u = new DB('shop_brand', 'sb');
            $u->select('sb.*, COUNT(sp.id) countGoods');
            $u->leftjoin('shop_price sp', 'sb.id=sp.id_brand');

            $searchStr = $this->input["searchText"];
            $searchArr = explode(' ', $searchStr);

            if (!empty($searchStr)) {
                foreach ($searchArr as $searchItem) {
                    if (!empty($search))
                        $search .= " AND ";
                    $search .= "(sb.code LIKE '%$searchItem%' OR sb.name LIKE '%$searchItem%')";
                }
                $u->where($search);
            }

            $patterns = array('id' => 'sb.id',
                'code' => 'sb.code',
                'article' => 'sp.article',
                'name' => 'sb.name'
            );

            $sortBy = (isset($patterns[$this->sortBy])) ? $patterns[$this->sortBy] : 'id';

            if ($this->sortOrder == 'desc')
                $u->orderby($sortBy, 1);
            else $u->orderby($sortBy, 0);
            $u->groupby('sb.id');

            $objects = $u->getList();
            foreach ($objects as $item) {
                $brand = null;
                $brand['id'] = $item['id'];
                $brand['code'] = $item['code'];
                $brand['name'] = $item['name'];
                $brand['title'] = $item['name'];
                $brand['imageFile'] = $item['image'];
                $brand['description'] = $item['text'];
                $brand['seoHeader'] = $item['title'];
                $brand['seoKeywords'] = $item['keywords'];
                $brand['seoDescription'] = $item['description'];
                $brand['countGoods'] = (int)$item['countGoods'];
                if ($brand['imageFile']) {
                    if (strpos($brand['imageFile'], "://") === false) {
                        $brand['imageUrl'] = 'http://' . $this->hostname . "/images/rus/shopbrand/" . $brand['imageFile'];
                        $brand['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/shopbrand/" . $brand['imageFile'];
                    } else {
                        $brand['imageUrl'] = $brand['imageFile'];
                        $brand['imageUrlPreview'] = $brand['imageFile'];
                    }
                }
                $items[] = $brand;
            }

            $this->result['count'] = sizeof($items);
            $this->result['items'] = $items;

        } catch (Exception $e) {
            $this->error = "Не удаётся получить список брендов!";
        }

        return $this;
    }

    // информация
    public function info($id = NULL)
    {
        try {
            $u = new DB('shop_brand', 'sb');
            $brand = $u->getInfo($this->input["id"]);
            $brand['imageFile'] = $brand['image'];
            $brand['seoHeader'] = $brand['title'];
            $brand['seoKeywords'] = $brand['keywords'];
            $brand['seoDescription'] = $brand['description'];
            $brand['description'] = $brand['text'];
            if ($brand['imageFile']) {
                if (strpos($brand['imageFile'], "://") === false) {
                    $brand['imageUrl'] = 'http://' . $this->hostname . "/images/rus/shopbrand/" . $brand['imageFile'];
                    $brand['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/shopbrand/" . $brand['imageFile'];
                } else {
                    $brand['imageUrl'] = $brand['imageFile'];
                    $brand['imageUrlPreview'] = $brand['imageFile'];
                }
            }
            $this->result = $brand;
        } catch (Exception $e) {
            $this->error = "Не удаётся получить информацию о бренде!";
        }
        return $this;
    }

    // получить код
    private function getCode($id, $title, $code)
    {
        if (empty($code))
            $code = strtolower(se_translite_url($title));
        $u = new DB('shop_brand', 'sb');
        $u->select('sb.id, sb.code');
        $i = 2;
        $code_n = $code;
        while ($i < 1000) {
            $s = "sb.code='$code_n'";
            if ($id)
                $s .= " AND sb.id<>$id";
            $result = $u->findList($s)->fetchOne();
            if ($result["id"])
                $code_n = $code . $i;
            else return $code_n;
            $i++;
        }
    }


    // сохранить
    public function save()
    {
        try {
            $this->input["code"] = $this->getCode($this->input["id"], $this->input["name"], $this->input["code"]);
            if (isset($this->input["imageFile"]))
                $this->input["image"] = $this->input["imageFile"];
            if (isset($this->input["description"]))
                $this->input["text"] = $this->input["description"];
            if (isset($this->input["seoHeader"]))
                $this->input["title"] = $this->input["seoHeader"];
            if (isset($this->input["seoKeywords"]))
                $this->input["keywords"] = $this->input["seoKeywords"];
            if (isset($this->input["seoDescription"]))
                $this->input["description"] = $this->input["seoDescription"];

            $u = new DB('shop_brand', 'sb');
            $u->setValuesFields($this->input);
            $this->input["id"] = $u->save();
            $this->info();
            return $this;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить бренд!";
        }
    }
}
