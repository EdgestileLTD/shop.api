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

    /* @var $sendPulseApi SendPulseApi */
    private $sendPulseApi;

    public function save()
    {
        DB::query("UPDATE email_providers SET is_active = FALSE");
        return parent::save();
    }

    public function providerEnable()
    {
        return ($this->providerName == "sendpulse" && !empty($this->settingsProvider["SECRET"]["value"]));
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

    /* @return SendpulseApi */
    public function getInstanceSendPulseApi()
    {
        if (empty($this->settingsProvider)) return false;
        if (!$this->sendPulseApi)
            $this->sendPulseApi = new SendpulseApi($this->settingsProvider["ID"]["value"],
                $this->settingsProvider["SECRET"]["value"], "session");
        return $this->sendPulseApi;
    }

    public function createAddressBook($bookName)
    {
        $this->initProvider();
        if (!empty($this->settingsProvider) && $this->providerName == "sendpulse") {
            return $this->getInstanceSendPulseApi()->createAddressBook($bookName)->id;
        }
    }

    public function removeAddressBook($idBook)
    {
        $this->initProvider();
        if (!empty($this->settingsProvider) && $this->providerName == "sendpulse")
            $this->getInstanceSendPulseApi()->removeAddressBook($idBook);
    }

    public function addEmails($idsBooks = array(), $emails = array())
    {
        $this->initProvider();
        if (!empty($this->settingsProvider) && $this->providerName == "sendpulse") {
            foreach ($idsBooks as $idBook)
                $this->getInstanceSendPulseApi()->addEmails($idBook, $emails);
        }
    }

    public function removeEmails($idsBooks = array(), $emails = array())
    {
        $this->initProvider();
        if (!empty($this->settingsProvider) && $this->providerName == "sendpulse") {
            foreach ($idsBooks as $idBook)
                $this->getInstanceSendPulseApi()->removeEmails($idBook, $emails);
        }
    }

    public function fetch()
    {
        parent::fetch();
        foreach($this->result['items'] as &$item) {
            if ($item['name'] == 'sendpulse') {
                $settings = $item['settings'];
                if (!empty($item['settings'])) {
                    $settings = json_decode($item['settings'], true);
                } else {
                    $settings = array();
                }
                if (empty($settings['SECRET']['value']))
                    $item['url'] = "https://sendpulse.com/ru/?ref=700621";
            }
        }
    }

    public function removeEmailFromAllBooks($email)
    {
        $this->initProvider();
        if (!empty($this->settingsProvider) && $this->providerName == "sendpulse")
            $this->getInstanceSendPulseApi()->removeEmailFromAllBooks($email);
    }

    public function createCampaign($subject, $body, $idBook, $sendDate)
    {
        $this->initProvider();
        $m = new Main();
        $info = $m->info();

        $senderName = $info["shopname"];
        $senderEmail = $info["esales"];
        if (!empty($this->settingsProvider) && $this->providerName == "sendpulse") {
            $senders = $this->getInstanceSendPulseApi()->listSenders();
            $isExist = false;
            $senderEmailDef = null;
            foreach ($senders as $sender) {
                $senderEmailDef = empty($senderEmailDef) ? $sender->email : $senderEmailDef;
                if ($isExist = ($sender->email == $senderEmail))
                    break;
            }
            if (!$isExist) {
                $this->getInstanceSendPulseApi()->addSender($senderName, $senderEmail);
                $senderEmail = $senderEmailDef;
            }
            $this->getInstanceSendPulseApi()->createCampaign($senderName, $senderEmail,
                $subject, $body, $idBook, $sendDate);
        }
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