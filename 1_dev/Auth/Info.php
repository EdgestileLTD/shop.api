<?php

$status = array();

$userToken = md5($json->login . $json->password);

function getMySQLVersion()
{
    $r = se_db_query("select version()");
    $answer = se_db_fetch_row($r);
    if ($answer) {
        $version = explode(".", $answer[0]);
        if (count($version) > 1) {
            return (int)$version[0] . $version[1];
        }
    }
    return 50;
}

function correctFileUpdateForMySQL56($fileName)
{
    file_put_contents($fileName, str_replace(" ON UPDATE CURRENT_TIMESTAMP", "", file_get_contents($fileName)));
}

function getPermission($idUser) {
    if (!$idUser)
        return array();

    $u = new seTable('permission_object','po');
    $u->select('po.*, BIT_OR(por.mask) mask');
    $u->leftjoin('permission_object_role por', 'por.id_object = po.id');
    $u->leftjoin('permission_role_user pru', 'pru.id_role = por.id_role');
    $u->where('pru.id_user = ?', $idUser);
    $u->groupby('po.id');
    $objects = $u->getList();
    foreach($objects as $item) {
        $object = null;
        $object['id'] = $item['id'];
        $object['code'] = $item['code'];
        $object['mask'] = (int) $item['mask'];
        $items[] = $object;
    }
    return $items;
}

if (trim($json->token) == trim($userToken)) {
    $data['userDisplay'] = 'Администратор';
    $data['idUser'] = "admin";
    $data["isUserAdmin"] = true;
}
else {
    $u = new seTable("se_user", "su");
    $u->select('su.id, su.is_super_admin, sug.group_id, 
                CONCAT_WS(" ", p.last_name, CONCAT_WS(".", SUBSTR(p.first_name, 1, 1), SUBSTR(p.sec_name, 1, 1))) displayName');
    $u->innerjoin('person p', 'p.id = su.id');
    $u->leftjoin('se_user_group sug', 'sug.user_id = su.id AND sug.group_id = 3');
    $u->where('is_active="Y" AND username="?"', $json->login);
    $u->andWhere('password="?"', strtolower($json->password));
    $result = $u->fetchOne();
    if (!empty($result)) {
        $data['userDisplay'] = $result["displayName"];
        $_SESSION['idUser'] = $data['idUser'] = $result["id"];
        $_SESSION['isUserAdmin'] = $data["isUserAdmin"] = $result["group_id"] == 3;
    } else $error = 'Неправильное имя пользователя или пароль!';
}

if (!empty($error)) {
    $status['status'] = 'error';
    $status['error'] = $error;
    outputData($status);
    exit;
}

$u = new seTable("main", "m");
$u->select("*");
$u->orderby("id");

$shops = array();
$result = $u->getList();

$data['hostname'] = $json->hostname;
$settings = new seTable('se_settings', 'ss');
if (!$settings->isFindField("db_version"))
    $settings->addField("db_version", "mediumint(9) DEFAULT 0");

$settings->select("db_version");
$settings->fetchOne();
$db_version = $settings->db_version;
if (empty($db_version))
    se_db_query("INSERT INTO se_settings (`version`, `db_version`) VALUE (1, 1)");

if ($db_version < DB_VERSION) {
    $pathRoot = $_SERVER['DOCUMENT_ROOT'] . '/api/update/scripts/';
    for ($i = $settings->db_version + 1; $i <= DB_VERSION; $i++) {
        $fileUpdate = $pathRoot . $i . '.php';
        if (file_exists($fileUpdate)) {
            if (getMySQLVersion() < 56)
                correctFileUpdateForMySQL56($fileUpdate);
            require_once $fileUpdate;
            se_db_query("UPDATE se_settings SET db_version=$i");
        }
    }
}

$root = API_ROOT;
if (IS_EXT)
    $dir = '../app-data/';
else $dir = '../app-data/' . $json->hostname;
if (!file_exists($root . $dir)) {
    $dirs = explode('/', $dir);
    $path = $root;
    foreach ($dirs as $d) {
        $path .= $d;
        if (!file_exists($path))
            mkdir($path, 0700);
        $path .= '/';
    }
}

$tables = array();
if (!IS_EXT) {
    $stmt = se_db_query("SHOW TABLES");
    $rows = mysqli_fetch_all($stmt);
    foreach ($rows as $row)
        $tables[] = $row[0];
}

$data['permission'] = getPermission($data['idUser']);
$data['tables'] = $tables;

if (empty($error)) {
    $_SESSION['isAuth'] = true;
    $status['status'] = 'ok';
    $status['data'] = $data;
} else {
    $status['status'] = 'error';
    $status['error'] = $error;
}
outputData($status);