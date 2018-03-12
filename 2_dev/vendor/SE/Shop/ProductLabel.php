<?php


namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class ProductLabel extends Base
{
    protected $tableName = "shop_label";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sl.*, sl.image image_file',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'shop_label_product spl',
                    "condition" => 'spl.id_label = sl.id'
                ),
                array(
                    "type" => "left",
                    "table" => 'shop_price sp',
                    "condition" => 'sp.id = spl.id_product'
                )
            )
        );
    }

    protected function correctItemsBeforeFetch($items = [])
    {
        foreach ($items as &$item) {
            $item['imageFile'] = $item['image'];
            if ($item['imageFile']) {
                if (strpos($item['imageFile'], "://") === false) {
                    $item['imageUrl'] = 'http://' . $this->hostname . "/images/rus/labels/" . $item['imageFile'];
                    $item['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/labels/" . $item['imageFile'];
                } else {
                    $item['imageUrl'] = $item['imageFile'];
                    $item['imageUrlPreview'] = $item['imageFile'];
                }
            }
        }

        return $items;
    }

    protected function getAddInfo()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $result["products"] = $this->getProducts();
        return $result;
    }

    public function getProducts($idLabel = null)
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        $id = $idLabel ? $idLabel : $this->input["id"];
        if (!$id)
            return array();

        $u = new DB('shop_label_product', 'slp');
        $u->select('sp.id, sp.code, sp.article, sp.name, sp.price, sp.curr, sp.measure, sp.presence_count');
        $u->innerJoin("shop_price sp", "slp.id_product = sp.id");
        $u->where("slp.id_label = ?", $id);
        $u->groupBy("sp.id");

        return $u->getList();
    }

    protected function correctValuesBeforeSave()
    {
        $this->input["image"] = $this->input["imageFile"];
        return parent::correctValuesBeforeSave(); // TODOО: Change the autogenerated stub
    }

    protected function saveAddInfo()
    {
        return $this->saveProducts();
    }

    private function saveProducts()
    {
        $this->debugging('funct', __FUNCTION__.' '.__LINE__, __CLASS__, '[comment]');
        try {
            DB::saveManyToMany($this->input["id"], $this->input["products"],
                array("table" => "shop_label_product", "key" => "id_label", "link" => "id_product"));
            return true;
        } catch (Exception $e) {
            $this->error = "Не удаётся сохранить назначенные товары для ярлыка!";
            throw new Exception($this->error);
        }
    }

}