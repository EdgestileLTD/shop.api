<?php

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (!empty($_GET))
    $method = $_GET["method"];

function createZip($source, $destination)
{
    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    if (!is_array($source))
        $sources[] = $source;
    else $sources = $source;

    foreach ($sources as $source) {
        $root = $source;
        $source = str_replace('\\', '/', realpath($source));
        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
                    continue;

                $file = realpath($file);
                $file = str_replace('\\', '/', $file);
                if (is_dir($file) === true) {
                    $zip->addEmptyDir($root . "/" . str_replace($source . '/', '', $file . '/'));
                } else if (is_file($file) === true) {
                    $zip->addFromString($root . "/" . str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } else if (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }
    }
    return $zip->close();
}

class Update
{

    private $urlUpdateFile;
    private $fileUpdate = "update_v1.zip";
    private $expireTime = 86400;

    function __construct($urlUpdateFile = null)
    {
        $this->urlUpdateFile = $urlUpdateFile;
    }

    function getVersion()
    {
        include "1/version.php";
        echo API_BUILD;
    }

    function createArchive()
    {
        if (file_exists($file = getcwd() . "/" . $this->fileUpdate))
            unlink($file);
        if (createZip(array("1", "2", "update"), $this->fileUpdate))
            return true;
    }

    function getArchive()
    {
        if (!file_exists($file = getcwd() . "/" . $this->fileUpdate) || ((time() - filemtime($file)) > $this->expireTime))
            $this->createArchive();

        if (file_exists($file))
            echo file_get_contents($file);
    }

    function exec()
    {
        if (empty($this->urlUpdateFile))
            return;

        file_put_contents($file = getcwd() . "/api/" . $this->fileUpdate, file_get_contents($this->urlUpdateFile . "?method=getArchive"));
        if (file_exists($file)) {
            $zip = new ZipArchive;
            if ($zip->open($file) === TRUE) {
                $zip->extractTo(getcwd() . "/api");
                $zip->close();
            }
        }
    }
}

if (!empty($method)) {
    $update = new Update();
    if (method_exists($update, $method))
        $update->$method();
}
