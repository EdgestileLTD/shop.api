<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class SmsLog extends Base
{
    protected $tableName = "sms_log";

    public function fetch()
    {
        $this->updateSmsStatuses();
        return parent::fetch();
    }

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

    private function updateSmsStatuses()
    {
        $providers = array("sms.ru", "qtelecom.ru");
        foreach ($providers as $provider) {
            $url = "http://" . HOSTNAME . "/lib/sms.php";
            $ch = curl_init($url);
            $data["serial"] = DB::$dbSerial;
            $data["db_password"] = DB::$dbPassword;
            $data["action"] = "status";
            $data["provider"] = $provider;
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
        }
    }

}