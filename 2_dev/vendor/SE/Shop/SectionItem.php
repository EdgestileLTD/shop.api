<?php

namespace SE\Shop;

use SE\DB;

class SectionItem extends Base
{
    protected $tableName = "shop_section_item";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'ssi.*, ssi.picture image_file',
        );
    }

    protected function getSettingsInfo()
    {
        return array(
            "select" => 'ssi.*, ssi.picture image_path',
        );
    }

    public function fetch()
    {
        parent::fetch();
        $items = $this->result['items'];
        foreach ($items as &$item) {
            $item = $this->detectTypes($item);
            $item['description'] = substr(str_replace("\r\n", ' ', strip_tags($item['description'])), 0, 50);
            if (isset($item['picture'])) {
                if (strpos($item['picture'], "://") === false) {
                    $item['imageUrl'] = 'http://' . $this->hostname . "/images/rus/sections/" . $item['picture'];
                    $item['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/sections/" . $item['picture'];
                } else {
                    $item['imageUrl'] = $item['imageUrlPreview'] = $item['picture'];
                }
            }
        }
        $this->result['items'] = $items;
    }

    public function info($id = null)
    {
        parent::info($id);
        //$this->result['imagePath'] = str_replace('//', '/', $this->result['imagePath']);
        if ($this->result['imageFile']) {
            if (strpos($this->result['imageFile'], "://") === false) {
                $this->result['imageUrl'] = 'http://' . $this->hostname . "/images/rus/sections/" . $this->result['imageFile'];
                $this->result['imageUrlPreview'] = "http://{$this->hostname}/lib/image.php?size=64&img=images/rus/sections/" . $this->result['imageFile'];
            } else {
                $this->result['imageUrl'] = $this->result['imageFile'];
                $this->result['imageUrlPreview'] = $this->result['imageFile'];
            }
        }

        return $this->result = $this->detectTypes($this->result);
    }

    /**
     *
     * @var array $sectionItem - Section Item
     * @return array
     */
    public function detectTypes($sectionItem){
        if(!empty($sectionItem['idGroup'])) $sectionItem['type'] = 'idGroup';
        if(!empty($sectionItem['idPrice'])) $sectionItem['type'] = 'idPrice';
        if(!empty($sectionItem['idBrand'])) $sectionItem['type'] = 'idBrand';
        if(!empty($sectionItem['idNew']))   $sectionItem['type'] = 'idNew';
        if(!isset($sectionItem['type']))    $sectionItem['type'] = 'url';

        if(isset($sectionItem['type'])){
            switch($sectionItem['type']){
                case 'idPrice':
                    $DB = new DB('shop_price','sp');
                    $DB->select('sp.name');
                    $DB->where('sp.id = ? ', $sectionItem['idPrice']);
                    $sectionItem['priceName'] = $DB->fetchOne()['name'];
                    break;
                case 'idGroup':
                    $DB = new DB('shop_group','sg');
                    $DB->select('sg.name');
                    $DB->where('sg.id = ? ', $sectionItem['idGroup']);
                    $sectionItem['nameGroup'] = $DB->fetchOne()['name'];
                    break;
                case 'idBrand':
                    $DB = new DB('shop_brand','sb');
                    $DB->select('sb.name');
                    $DB->where('sb.id = ? ', $sectionItem['idBrand']);
                    $sectionItem['nameBrand'] = $DB->fetchOne()['name'];
                    break;
                case 'idNew':
                    $DB = new DB('news','n');
                    $DB->select('n.title');
                    $DB->where('n.id = ? ', $sectionItem['idNew']);
                    $sectionItem['nameNews'] = $DB->fetchOne()['title'];
                    break;
            }
        }
        return $this->nullTypes($sectionItem);
    }

    private function nullTypes($sectionItem = array()){
        $type = $sectionItem['type'];
        if($type != 'idGroup')
            $sectionItem['idGroup'] = NULL;

        if($type != 'idPrice')
            $sectionItem['idPrice'] = NULL;

        if($type != 'idBrand')
            $sectionItem['idBrand'] = NULL;

        if($type != 'idNew')
            $sectionItem['idNew'] = NULL;
        return $sectionItem;
    }
}