<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class EmailProvider extends Base
{
    protected $tableName = "email_providers";

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

    public function createAddressBook($group)
    {
        try {
            $idGroup = $group["id"];
            $bookName = $group["name"];
            $u = new DB("email_providers");
            $u->where("is_active");
            $result = $u->fetchOne();
            if ($result) {
                $id = $this->requestSmsProviderInfo($result["name"], "createBook", ["name" => $bookName]);
                $data["id"] = $idGroup;
                $data["email_settings"] = json_encode(["idBook" => $id]);
                $u = new DB("se_group");
                $u->setValuesFields($data);
                $u->save();
            }
        } catch (Exception $e) {
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