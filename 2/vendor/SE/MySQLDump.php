<?php

namespace SE;

use \PDO as PDO;

class MySQLDump
{
    const MAX_SQL_SIZE = 1e6;

    const NONE = 0;
    const DROP = 1;
    const CREATE = 2;
    const DATA = 4;
    const TRIGGERS = 8;
    const ALL = 15; // DROP | CREATE | DATA | TRIGGERS

    /** @var array */
    public $tables = array(
        '*' => self::ALL,
    );

    /*
     * Saves dump to the file.
     * @param  string filename
     * @return void
     */
    public function save($file)
    {
        $handle = strcasecmp(substr($file, -3), '.gz') ? fopen($file, 'wb') : gzopen($file, 'wb');
        if (!$handle) {
            throw new Exception("ERROR: Cannot write file '$file'.");
        }
        $this->write($handle);
    }

    /*
     * Writes dump to logical file.
     * @param  resource
     * @return void
     */
    public function write($handle = NULL)
    {
        if ($handle === NULL) {
            $handle = fopen('php://output', 'wb');
        } elseif (!is_resource($handle) || get_resource_type($handle) !== 'stream') {
            throw new Exception('Argument must be stream resource.');
        }

        $tables = $views = array();

        $res =  DB::query('SHOW FULL TABLES');
        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            if ($row[1] === 'VIEW') {
                $views[] = $row[0];
            } else {
                $tables[] = $row[0];
            }
        }

        $tables = array_merge($tables, $views); // views must be last

        DB::query('LOCK TABLES `' . implode('` READ, `', $tables) . '` READ');

        $db = DB::query('SELECT DATABASE()')->fetch(PDO::FETCH_NUM);
        fwrite($handle, "-- Created at " . date('j.n.Y G:i') . " using SiteEdit MySQL Dump Utility\n"
            . (isset($_SERVER['HTTP_HOST']) ? "-- Host: $_SERVER[HTTP_HOST]\n" : '')
            . "-- Database: " . $db[0] . "\n"
            . "\n"
            . "SET NAMES utf8;\n"
            . "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n"
            . "SET FOREIGN_KEY_CHECKS=0;\n"
        );

        foreach ($tables as $table) {
            $this->dumpTable($handle, $table);
        }

        fwrite($handle, "-- THE END\n");
        DB::query('UNLOCK TABLES');
    }


    /*
     * Dumps table to logical file.
     * @param  resource
     * @return void
     */
    public function dumpTable($handle, $table)
    {
        $delTable = $this->delimite($table);
        $res = DB::query("SHOW CREATE TABLE $delTable");
        $row = $res->fetch(PDO::FETCH_ASSOC);

        fwrite($handle, "-- --------------------------------------------------------\n\n");

        $mode = isset($this->tables[$table]) ? $this->tables[$table] : $this->tables['*'];
        $view = isset($row['Create View']);

        if ($mode & self::DROP) {
            fwrite($handle, 'DROP ' . ($view ? 'VIEW' : 'TABLE') . " IF EXISTS $delTable;\n\n");
        }

        if ($mode & self::CREATE) {
            fwrite($handle, $row[$view ? 'Create View' : 'Create Table'] . ";\n\n");
        }

        if (!$view && ($mode & self::DATA)) {
            $numeric = array();
            $res = DB::query("SHOW COLUMNS FROM $delTable");
            $cols = array();
            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $col = $row['Field'];
                $cols[] = $this->delimite($col);
                $numeric[$col] = (bool)preg_match('#^[^(]*(BYTE|COUNTER|SERIAL|INT|LONG$|CURRENCY|REAL|MONEY|FLOAT|DOUBLE|DECIMAL|NUMERIC|NUMBER)#i', $row['Type']);
            }
            $cols = '(' . implode(', ', $cols) . ')';

            $size = 0;
            $res = DB::query("SELECT * FROM {$delTable}");

            while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                $s = '(';
                foreach ($row as $key => $value) {
                    if ($value === NULL) {
                        $s .= "NULL,\t";
                    } elseif ($numeric[$key]) {
                        $s .= $value . ",\t";
                    } else {
                        $s .= DB::quote($value) . ",\t";
                    }
                }

                if ($size == 0) {
                    $s = "INSERT INTO $delTable $cols VALUES\n$s";
                } else {
                    $s = ",\n$s";
                }

                $len = strlen($s) - 1;
                $s[$len - 1] = ')';
                fwrite($handle, $s, $len);

                $size += $len;
                if ($size > self::MAX_SQL_SIZE) {
                    fwrite($handle, ";\n");
                    $size = 0;
                }
            }

            if ($size) {
                fwrite($handle, ";\n");
            }
            fwrite($handle, "\n");
        }

        if ($mode & self::TRIGGERS) {
            $res = DB::query("SHOW TRIGGERS LIKE '{$table}'");
            if ($res->rowCount()) {
                fwrite($handle, "DELIMITER ;;\n\n");
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                    fwrite($handle, "CREATE TRIGGER {$this->delimite($row['Trigger'])} $row[Timing] $row[Event] ON $delTable FOR EACH ROW\n$row[Statement];;\n\n");
                }
                fwrite($handle, "DELIMITER ;\n\n");
            }
        }

        fwrite($handle, "\n");
    }


    private function delimite($s)
    {
        return '`' . str_replace('`', '``', $s) . '`';
    }

}
