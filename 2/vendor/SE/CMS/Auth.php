<?php

namespace SE\CMS;

use SE\DB as DB;
use SE\Exception;

class Auth extends Base
{

    // получить разрешение
    public function getPermission($idUser)
    {
        if (!$idUser)
            return array();

        try {
            $u = new DB('permission_object', 'po');
            $u->select('po.*, BIT_OR(por.mask) mask');
            $u->leftJoin('permission_object_role por', 'por.id_object = po.id');
            $u->leftJoin('permission_role_user pru', 'pru.id_role = por.id_role');
            $u->where('pru.id_user = ?', $idUser);
            $u->groupBy('po.id');
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список прав пользователя!";
            throw new Exception($this->error);
        }
    }

    // получить id валюты
    private function getIdCurrency()
    {
        $u = new DB('shop_currency', 'sc');
        $u->select("id");
        $u->where("is_main");
        return $u->fetchOne()["id"];
    }

    // получить id типа цены
    private function getIdTypePrice()
    {
        $t = new DB("shop_typeprice");
        $t->select("id");
        $t->where("code = 'retail'");
        return $t->fetchOne()["id"];
    }

    // информация
    public function info($id = null)
    {
        try {
            if (trim($this->input["login"]) == DB::$dbSerial &&
                trim($this->input["hash"]) == md5(DB::$projectKey)
            ) {
                $data['userDisplay'] = 'Администратор';
                $data['isAdmin'] = true;
                $data['hostname'] = $this->hostname;
                $data['seFolder'] = 'www';
                $authData["login"] = $this->input["login"];
                $authData["hash"] = $this->input["hash"];
                $data['config'] = $authData;
                //$data['permissions'] = $this->getPermission($data['idUser']);

                $_SESSION["login"] = $this->input["login"];
                $_SESSION["hash"] = $this->input["hash"];
                $_SESSION['isAuth'] = true;
                $_SESSION['hostname'] = HOSTNAME;
                $_SESSION['idLang'] = 1;
                $_SESSION['seFolder'] = $data['seFolder'];
                $_SESSION['idCurrency'] = $this->getIdCurrency();
                $_SESSION["idTypePrice"] = $this->getIdTypePrice();
                $_SESSION["idWarehouse"] = 1;

                $this->result = $data;
            } else {
                $this->error = 'Неправильное имя пользователя или пароль!';
                throw new Exception($this->error);
            }
        } catch (Exception $e) {
            $this->error = "Ошибка при авторизации!";
        }
    }

    // получить
    public function get()
    {
        $this->result["permissions"] = $this->getPermission($_SESSION['idUser']);
    }
}