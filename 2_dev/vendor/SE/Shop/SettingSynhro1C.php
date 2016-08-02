<?php

namespace SE\Shop;

class SettingSynhro1C extends Base
{
    public function info()
    {
        $url = AUTH_SERVER . "/api/2/SettingSynhro1C/Info.api";
        $ch = curl_init($url);
        $data["login"] = $_SESSION["login"];
        $data["hash"] = $_SESSION["hash"];
        $apiData = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($apiData))
        );
        $result = json_decode(curl_exec($ch), 1);
        if ($result["status"] == "ok") {
            $this->result = $result["data"];
            return $result["data"];
        } else {
            $this->error = "Не удаётся получить данные по синхронизации с 1С!";
            return null;
        }
    }

    public function save()
    {
        $url = AUTH_SERVER . "/api/2/SettingSynhro1C/Save.api";
        $ch = curl_init($url);
        $data["project"] =  str_replace(".e-stile.ru", "", HOSTNAME);
        $data["login"] = $_SESSION["login"];
        $data["hash"] = $_SESSION["hash"];
        $apiData = json_encode($data);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apiData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($apiData))
        );
        $result = json_decode(curl_exec($ch), 1);
        if ($result["status"] == "ok")
            return $result["data"];
        return null;
    }

    public function delete()
    {

    }

    public function fetch()
    {

    }

}