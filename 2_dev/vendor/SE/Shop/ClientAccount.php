<?php

namespace SE\Shop;

use SE\DB;
use SE\Exception;

// счет клиента
class ClientAccount extends Base
{
    protected $tableName = "se_user_accounts";
    protected $sortOrder = "sua";
}