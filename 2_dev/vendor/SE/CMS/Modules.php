<?php

namespace SE\CMS;

class Modules extends Base
{
    function __construct($input)
    {

    }

    public function fetch()
    {
        $items = array();
        $itams[] = array('title'=>'Текст', 'icon'=>'', 'childs'=>array('atext'=>array('title'=>'Текст и рисунок', 'description'=>'Адаптивный текст и рисунок', 'icon'=>'')));
        $itams[] = array('title'=>'Обратная связь', 'icon'=>'', 'childs'=>array('amail'=>array('title'=>'Форма обратной связи', 'description'=>'Форма обратной связи', 'icon'=>'')));



        $this->result = array('items' => $items, 'count' => count($items));
    }
}