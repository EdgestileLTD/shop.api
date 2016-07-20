<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class Auth extends Base
{

    private function getMySQLVersion()
    {
        $r = DB::query("select version()");
        $answer = $r->fetchAll();
        if ($answer) {
            $version = explode(".", $answer[0]);
            if (count($version) > 1) {
                return (int)$version[0] . $version[1];
            }
        }
        return 50;
    }

    private function correctFileUpdateForMySQL56($fileName)
    {
        file_put_contents($fileName, str_replace(" ON UPDATE CURRENT_TIMESTAMP", "", file_get_contents($fileName)));
    }

    private function getPermission($idUser)
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

    private function getAccounts()
    {
        $idMultiAccounts = $this->input["idMultiAccounts"];
        if (!$idMultiAccounts)
            return array();
    }

    public function info()
    {
        $userToken = md5($this->input["login"] . $this->input["password"]);
        try {
            if (TOKEN == trim($userToken)) {
                $data['userDisplay'] = 'Администратор';
                $data['isAdmin'] = true;
            } else {
                $u = new DB("se_user", "su");
                $u->select('su.id,
                    CONCAT_WS(" ", p.last_name, CONCAT_WS(".", SUBSTR(p.first_name, 1, 1), SUBSTR(p.sec_name, 1, 1))) displayName');
                $u->innerJoin('person p', 'p.id=su.id');
                $u->where('is_active="Y" AND username="?"', $this->input["login"]);
                $u->andWhere('password="?"', strtolower($this->input["password"]));
                $result = $u->fetchOne();
                if (!empty($result)) {
                    $data['userDisplay'] = $result["displayName"];
                    $data['idUser'] = $result["id"];
                } else $this->error = 'Неправильное имя пользователя или пароль!';
            }

            if (!empty($this->error))
               return $this;

            $u = new DB("main", "m");
            $u->select("*");
            $u->orderBy("id");

            $data['hostname'] = $this->hostname;
            $settings = new DB('se_settings', 'ss');
            $settings->select("db_version");
            $result = $settings->fetchOne();
            if (empty($result["dbVersion"]))
                DB::query("INSERT INTO se_settings (`version`, `db_version`) VALUE (1, 1)");

            if ($result["dbVersion"] < DB_VERSION) {
                $pathRoot = $_SERVER['DOCUMENT_ROOT'] . '/api/update/sql/';
                for ($i = $settings->db_version + 1; $i <= DB_VERSION; $i++) {
                    $fileUpdate = $pathRoot . $i . '.php';
                    if (file_exists($fileUpdate)) {
                        if ($this->getMySQLVersion() < 56)
                            $this->correctFileUpdateForMySQL56($fileUpdate);
                        require_once $fileUpdate;
                        DB::query("UPDATE se_settings SET db_version=$i");
                    }
                }
            }

            $data['permissions'] = $this->getPermission($data['idUser']);
            $data['accounts'] = $this->getAccounts();
            $_SESSION['idUser'] = $data['idUser'];
            if (empty($error))
                $_SESSION['isAuth'] = true;
            $this->result = $data;

        } catch (Exception $e) {
            $this->error = "Ошибка при авторизации!";
        }
    }

}