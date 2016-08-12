<?php

namespace SE\Shop;

use SE\DB as DB;
use SE\Exception;

class SmsProvider extends Base
{
    protected $tableName = "sms_providers";

    public function save()
    {
        DB::query("UPDATE sms_providers SET is_active = FALSE");
        return parent::save();
    }
}