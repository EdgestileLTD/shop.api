<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;
use SendPulse\SendpulseApi as SendPulseApi;

class EmailProvider extends Base
{
    protected $tableName = "email_providers";

    private $providerName;
    private $settingsProvider;

    public function save()
    {
        DB::query("UPDATE email_providers SET is_active = FALSE");
        return parent::save();
    }

    public function info()
    {
        parent::info();
        $this->result["balance"] = $this->getBalance();
        return $this->result;
    }

    public function initProvider()
    {
        $u = new DB("email_providers");
        $u->where("is_active");
        $result = $u->fetchOne();
        if ($result) {
            $this->providerName = strtolower($result["name"]);
            $this->settingsProvider = json_decode($result["settings"], 1);
        }
    }

    /*
     *  @return SendpulseApi
     */
    public function getInstanceSendPulseApi()
    {
        return new SendpulseApi($this->settingsProvider["ID"]["value"],
            $this->settingsProvider["SECRET"]["value"], "session");
    }

    public function createAddressBook($bookName)
    {
        $this->initProvider();
        if ($this->providerName == "sendpulse")
            return $this->getInstanceSendPulseApi()->createAddressBook($bookName)->id;
    }

    public function removeAddressBook($idBook)
    {
        $this->initProvider();
        if ($this->providerName == "sendpulse")
            $this->getInstanceSendPulseApi()->removeAddressBook($idBook);
    }

    public function addEmails($idBooks = array(), $emails = array())
    {

    }

    private function getBalance()
    {
        return (float)$this->requestSmsProviderInfo($this->result["name"], "balance");
    }

    private function requestSmsProviderInfo($provider, $action, $parameters = null)
    {
        $url = "http://" . HOSTNAME . "/lib/esp.php";
        $ch = curl_init($url);
        $data["serial"] = DB::$dbSerial;
        $data["db_password"] = DB::$dbPassword;
        $data["provider"] = $provider;
        $data["action"] = $action;
        if ($parameters)
            $data["parameters"] = json_encode($parameters);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        return curl_exec($ch);
    }
}