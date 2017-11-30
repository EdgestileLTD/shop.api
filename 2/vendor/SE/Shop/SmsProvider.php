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

    public function info($id = NULL)
    {
        parent::info();
        $this->result["balance"] = $this->getBalance();
        $this->result["description"] = $this->getDescription();
        return $this->result;
    }

    private function getBalance()
    {
        return $this->requestSmsProviderInfo($this->result["name"], "balance");
    }

    private function getDescription()
    {
        if ($this->result["name"] == 'inCore Dev')
        {
            return array("Для начала работы с установленным модулем \"InCore Dev: SMS-уведомления\" необходимо пройти регистрацию(ссылка на https://siteedit4.incore1.ru/ru/reg.html) " .
            "на сервисе InCore Dev и дождаться активации аккаунта, которая происходит с понедельника по пятницу с 9.00 до 18.00 по московскому времени.",
            "Стоимость смс с именем отправителя тарифицируется - 1,5 руб. Стоимость смс без имени отправителя - 1 руб. " .
            "Для использования в смс уникального имени отправителя необходимо согласование с вашим менеджером сервиса InCore Dev.");
        }
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