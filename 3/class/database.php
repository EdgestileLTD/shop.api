<?php

class database
{
    private $host = 'siteedit.beget.ru';
    private $user = 'edgestile_147155';
    private $pass = '8cfd1bb65e';
    private $dbname = 'edgestile_147155';

    private $dbh;
    private $stmt;
    private $transaction = true;
    public $error = '';

    private static $instance = null;

    private function __construct($config)
    {
        if (!empty($config)) {
            $this->host = $config['HostName'];
            $this->user = $config['DBUserName'];
            $this->pass = $config['DBPassword'];
            $this->dbname = $config['DBName'];
        }

        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname;
        $options = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            $this->error = 'Подключение к БД не удалось: ' . $e->getMessage();
        }

        return $this;
    }

    public static function getInstance($config = array())
    {
        if (self::$instance === null) self::$instance = new self($config);
        return self::$instance;
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    public function __destruct()
    {
        $this->dbh = null;
    }

    public function filterParams($allowed = array(), $input = array())
    {
        foreach ($input as $k => $v) {
            if (!in_array($k, $allowed)) {
                unset($input[$k]);
                continue;
            }
            $input[":" . $k] = $v;
            $key = array_search($k, $allowed);
            unset($input[$k], $allowed[$key]);
        }
        foreach ($allowed as $line) {
            $input[":" . $line] = '';
        }

        return (isset($input)) ? $input : array();
    }

    public function setParams($allowed = array(), $input = array())
    {
        $output = array();
        foreach ($input as $k => $v) {
            if (!in_array($k, $allowed)) {
                unset($input[$k]);
                continue;
            }
            $input[":" . $k] = $v;
            $output[] = "{$k}=:{$k}";
            $key = array_search($k, $allowed);
            unset($input[$k], $allowed[$key]);
        }

        return (!empty($output)) ? array($input, implode(", ", $output)) : array();
    }

    public function execute($sql = '', $params = array())
    {
        $this->stmt = $this->dbh->prepare($sql);
        if ($this->transaction) $this->dbh->beginTransaction();
        try {
            $this->stmt->execute($params);
            if (strpos(strtolower($sql), 'insert into') !== false) {
                $return = $this->dbh->lastInsertId();
                if ($this->transaction) $this->dbh->commit();
                return $return;
            }
            if ($this->transaction) $this->dbh->commit();

            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            if ($this->transaction) $this->dbh->rollBack();
            return false;
        }
    }

    public function fetch($sql = '', $params = array(), $first = false)
    {
        $this->execute($sql, $params);
        $rez = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        return (!empty($rez) && $first === true) ? $rez[0] : $rez;
    }

    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    public function lastInsertId()
    {
        return $this->dbh->lastInsertId();
    }

    public function startTransaction()
    {
        $this->dbh->beginTransaction();
    }

    public function cancelTransaction()
    {
        $this->dbh->rollBack();
    }

    public function endTransaction()
    {
        $this->dbh->commit();
    }

    public function inTransaction()
    {
        return $this->dbh->inTransaction();
    }

    public function setTransaction($arg = true)
    {
        $this->transaction = $arg;
    }
}