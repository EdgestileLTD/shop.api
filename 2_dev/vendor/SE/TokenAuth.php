<?php

namespace SE;

// авторизаця по токену
class TokenAuth extends Base
{
    // получать разрешение
    public function getPermission($idUser)
    {
        if (!$idUser)
            return array();

        // получение прав пользователя
        try {
            $u = new DB('permission_object', 'po');
            $u->select('po.*, BIT_OR(por.mask) mask');
            $u->leftJoin('permission_objec
            t_role por', 'por.id_object = po.id');
            $u->leftJoin('permission_role_user pru', 'pru.id_role = por.id_role');
            $u->where('pru.id_user = ?', $idUser);
            $u->groupBy('po.id');
            return $u->getList();
        } catch (Exception $e) {
            $this->error = "Не удаётся получить список прав пользователя!";
            throw new Exception($this->error);
        }
    }
    // запрос проекта у регистра
    public function getAuthData($data = array())
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


    // информация
    public function info()
    {
        // получение авторизационных данных
        $authData = $this->getAuthData($this->input); // $authData что возвращает?

        // при ошибке получения авторизационных данных
        if (!$authData) {
            $this->error = "Проект не найден или не активен!";
            return null;
        }

        try {
            // пробуем получить токен
            $token = md5(HOSTNAME . $this->input['serial'] . 'rs1e4tr54sFd'); // написать токен

            $authData['token'] = $token;
//            writeLog($authData);
//            writeLog($this->input);

            // если токен не совпадает с хешем - выдаем ошибку
            if ($token != $this->input['hash']) {
                $this->error = 'Неправильное имя пользователя или пароль!';
                throw new Exception($this->error);
            }


            /*
            // если админ
            if ($authData["isAdmin"]) {
                $data['userDisplay'] = 'Администратор';
                $data['isAdmin'] = true;
            // если пользователь не является админом - получаем логин, хеш
            } else {
                $u = new DB("se_user", "su");
                $u->select('su.id,
                    CONCAT_WS(" ", p.last_name, CONCAT_WS(".", SUBSTR(p.first_name, 1, 1), SUBSTR(p.sec_name, 1, 1))) displayName');
                $u->innerJoin('person p', 'p.id=su.id');
                $u->where('is_active="Y" AND username="?"', trim($this->input["login"])); // логин
                $u->andWhere('password="?"', strtolower($this->input["hash"])); // хеш
                $result = $u->fetchOne();

                // при наличии результата принять имя и id пользователя
                if (!empty($result)) {
                    $data['userDisplay'] = $result["displayName"];
                    $data['idUser'] = $result["id"];
                // при отсутствии результата вывести ошибку
                } else {
                    $this->error = 'Неправильное имя пользователя или пароль!';
                    throw new Exception($this->error);
                }
            }*/

            // папка с авторизационными данными
            $authData['seFolder'] = 'www'; // по аналогии сделать toten

            // при ошибке - передаем данные??????????????????????????
            if (!empty($this->error))
                return $this;

            // создаем новую БД
            $u = new DB("main", "m");
            $u->select("*");
            $u->orderBy("id");

            // получаем данные: хостнейм, настройки, версию бд
            $data['hostname'] = $this->hostname;
            $settings = new DB('se_settings', 'ss');
            $settings->select("db_version");
            $result = $settings->fetchOne();
            // при пустом значении версии-БД: вставить в SEнастройки версию и версию-БД // стоимость (1,1)???????
            if (empty($result["dbVersion"]))
                DB::query("INSERT INTO se_settings (`version`, `db_version`) VALUE (1, 1)");
            // если версия БД старше...
            if ($result["dbVersion"] < DB_VERSION) {
                $pathRoot =  $_SERVER['DOCUMENT_ROOT'] . '/api/update/sql/';
                DB::setErrorMode(\PDO::ERRMODE_SILENT);
                for ($i = $result["dbVersion"] + 1; $i <= DB_VERSION; $i++) {
                    $fileUpdate = $pathRoot . $i . '.sql';
                    if (file_exists($fileUpdate)) {
                        if ($this->getMySQLVersion() < 56)
                            $this->correctFileUpdateForMySQL56($fileUpdate);
                        $query = file_get_contents($fileUpdate);
                        try {
                            DB::query($query);
                            DB::query("UPDATE se_settings SET db_version=$i");
                        } catch (\PDOException $e) {
                            writeLog("Exception ERROR UPDATE {$i}.sql: ".$query);
                        }
                    }
                }
                DB::setErrorMode(\PDO::ERRMODE_EXCEPTION);
            }

            // принимаем авторизационные данные: логин, хеш, конфигурации, права, колво сайтов
            $authData["login"] = $this->input["login"];
            $authData["hash"] = $this->input["hash"];
            $data['config'] = $authData;
            $data['permissions'] = $this->getPermission($data['idUser']);
            $data['countSites'] = $authData['countSites'];
            // принимаем в сессию: логин,хеш, IDпользователя, колво сайтов, положительную авторизацию, имя хоста, токен
            $_SESSION["login"] = $this->input["login"];
            $_SESSION["hash"] = $this->input["hash"];
            $_SESSION['idUser'] = $data['idUser'];
            $_SESSION['countSites'] = $authData['countSites'];
            $_SESSION['isAuth'] = true;
            $_SESSION['hostname'] = HOSTNAME;
            $_SESSION['Token'] = HOSTNAME;

            // данные загружаем в результат
            $this->result = $data;

        } catch (Exception $e) {
            // при пойманом исключении выдать ошибку
            $this->error = "Ошибка при авторизации!";
        }
    }

    // получить
    public function get()
    {
        // получаем права доступа через ID пользователя
        $this->result["permissions"] = $this->getPermission($_SESSION['idUser']);
    }
}