<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class SectionPage extends Base
{
    protected $tableName = "shop_section_page";
    protected function getSettingsFetch()
    {
        return array(
            "select" => 'ssp.*, s.code',
            "joins" => array(
                array(
                    "type" => "inner",
                    "table" => '`shop_section` `s`',
                    "condition" => '`s`.`id` = `ssp`.`id_section`'
                ),
            )
        );
    }
}