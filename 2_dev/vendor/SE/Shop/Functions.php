<?php

namespace SE\Shop;

// функции
class Functions extends Base
{
    // трансляция?
    public function Translit()
    {
        $vars = $this->input["vars"];
        $i = 0;
        $items = array();
        foreach ($vars as $var) {
            $items[] = se_translite_url($var);
            $i++;
        }

        $this->result['count'] = $i;
        $this->result['items'] = $items;

        return $items;
    }
}