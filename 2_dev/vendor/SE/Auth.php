<?php

namespace SE;

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

    public function getPermission($idUser)
    {
        if (!$idUser)
            return [];

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

    public function getAuthData($data = [])
    {
        $url = AUTH_SERVER . "/api/2/Auth/Register.api";
        $ch = curl_init($url);
        $data["project"] =  str_replace(".e-stile.ru", "", HOSTNAME);
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


    public function info()
    {
        $authData = $this->getAuthData($this->input);
        if (!$authData) {
            $this->error = "Проект не найден или не активен!";
            return null;
        }

        try {
            if ($authData["isAdmin"]) {
                $data['userDisplay'] = 'Администратор';
                $data['isAdmin'] = true;
            } else {
                $u = new DB("se_user", "su");
                $u->select('su.id,
                    CONCAT_WS(" ", p.last_name, CONCAT_WS(".", SUBSTR(p.first_name, 1, 1), SUBSTR(p.sec_name, 1, 1))) displayName');
                $u->innerJoin('person p', 'p.id=su.id');
                $u->where('is_active="Y" AND username="?"', $this->input["login"]);
                $u->andWhere('password="?"', strtolower($this->input["hash"]));
                $result = $u->fetchOne();
                if (!empty($result)) {
                    $data['userDisplay'] = $result["displayName"];
                    $data['idUser'] = $result["id"];
                } else {
                    $this->error = 'Неправильное имя пользователя или пароль!';
                    throw new Exception($this->error);
                }
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
                $pathRoot =  $_SERVER['DOCUMENT_ROOT'] . '/api/update/sql/';
                DB::setErrorMode(\PDO::ERRMODE_SILENT);
                for ($i = $result["dbVersion"] + 1; $i <= DB_VERSION; $i++) {
                    $fileUpdate = $pathRoot . $i . '.sql';
                    if (file_exists($fileUpdate)) {
                        if ($this->getMySQLVersion() < 56)
                            $this->correctFileUpdateForMySQL56($fileUpdate);
                        $query = file_get_contents($fileUpdate);
                        DB::query($query);
                        DB::query("UPDATE se_settings SET db_version=$i");
                    }
                }
                DB::setErrorMode(\PDO::ERRMODE_EXCEPTION);
            }

            $authData["login"] = $this->input["login"];
            $authData["hash"] = $this->input["hash"];
            $data['config'] = $authData;
            $data['permissions'] = $this->getPermission($data['idUser']);

            $_SESSION["login"] = $this->input["login"];
            $_SESSION["hash"] = $this->input["hash"];
            $_SESSION['idUser'] = $data['idUser'];
            $_SESSION['isAuth'] = true;
            $_SESSION['hostname'] = HOSTNAME;

            $this->result = $data;

        } catch (Exception $e) {
            $this->error = "Ошибка при авторизации!";
        }
    }

    public function get()
    {
        $this->result["permissions"] = $this->getPermission($_SESSION['idUser']);
    }
}