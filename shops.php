<?php

chdir("/home/e/edgestile");

$rdir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), TRUE);

foreach ($rdir as $file)
{
    //echo str_repeat('---', $rdir->getDepth()).$file.'<br>';
}