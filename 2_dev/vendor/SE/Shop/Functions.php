<?php

namespace SE\Shop;


class Functions extends Base
{

    public function Translit()
    {
        $vars = $this->input["vars"];
        $i = 0;
        $items = [];
        foreach ($vars as $var) {
            $items[] = se_translite_url($var);
            $i++;
        }

        $this->result['count'] = $i;
        $this->result['items'] = $items;

        return $items;
    }
}