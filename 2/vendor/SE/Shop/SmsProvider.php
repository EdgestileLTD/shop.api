<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class SmsProvider extends Base
{
    protected $tableName = "sms_providers";

    public function save()
    {
        DB::query("UPDATE sms_providers SET is_active = FALSE");
        return parent::save();
    }

    public function info()
    {
        parent::info();
        $this->result["balance"] = $this->getBalance();
        return $this->result;
    }

    private function getBalance()
    {
        return $this->requestSmsProviderInfo($this->result["name"], "balance");
    }

    private function requestSmsProviderInfo($provider, $action)
    {
        $url = "http://" . HOSTNAME . "/lib/sms.php";
        $ch = curl_init($url);
        $data["serial"] = DB::$dbSerial;
        $data["db_password"] = DB::$dbPassword;
        $data["provider"] = $provider;
        $data["action"] = $action;
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        return curl_exec($ch);
    }
}