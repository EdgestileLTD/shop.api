<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class SmsLog extends Base
{
    protected $tableName = "sms_log";

    protected function getSettingsFetch()
    {
        return array(
            "select" => 'sl.*, sp.name provider, 
                IFNULL(CONCAT_WS(" ",  p.last_name, p.first_name, p.sec_name), "Администратор") user_name',
            "joins" => array(
                array(
                    "type" => "left",
                    "table" => 'person p',
                    "condition" => 'p.id = sl.id_user'
                ),
                array(
                    "type" => "inner",
                    "table" => 'sms_providers sp',
                    "condition" => 'sp.id = sl.id_provider'
                )
            )
        );
    }

}