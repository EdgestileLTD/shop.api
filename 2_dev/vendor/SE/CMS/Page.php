<?php

namespace SE\CMS;

class Page extends Base
{
    public function fetch()
    {
        $xml = simplexml_load_file($this->projectFolder . "/pages.xml");
        $this->result = $xml;
    }

}