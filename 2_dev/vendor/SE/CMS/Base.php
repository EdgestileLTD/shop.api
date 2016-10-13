<?php

namespace SE\CMS;

use SE\Base as CustomBase;

class Base extends CustomBase
{
    protected $pagesFile;

    function __construct($input)
    {
        parent::__construct($input);
    }
}