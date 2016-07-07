<?php

if (!empty($json->code)) {
    $conf_url = '/home/e/edgestile/se-24.com/projetcs/' . $json->code . '/config/config_db.php';
    if (!file_exists($conf_url)) {
	if (!is_dir('/home/e/edgestile/se-24.com/projetcs/' . $json->code)) {
	    mkdir('/home/e/edgestile/se-24.com/projetcs/' . $json->code);
	    mkdir('/home/e/edgestile/se-24.com/projetcs/' . $json->code . '/config');
	}

	$pass = md5('god' . time() . 'bless' . time() . 'you');
	$pass = substr($pass, 0, 10);

	$addon = '';
	switch (strlen($json->code)) {
	    case 3:
		$addon .= '0';
	    case 4:
		$addon .= '0';
	    case 5:
		$addon .= '0';
	}
	$data = '{"suffix":"' . $addon . $json->code . '","password":"' . $pass . '"}';
	$make_db = 'https://api.beget.ru/api/mysql/addDb?login=edgestile&passwd=FzCjgKah8&input_format=json&output_format=json&input_data=' . urlencode($data);
	file_get_contents($make_db);

	$list = file_get_contents('https://api.beget.ru/api/mysql/getList?login=edgestile&passwd=FzCjgKah8&output_format=json');
	$list = json_decode($list, 1);
	if ($list && $list['answer']['status'] == 'success') {
	    foreach ($list['answer']['result'] as $item) {
		if ($item['name'] == $json->code) {
		    $txt = '<?php' . "\r" .
			    '$CONFIG["DBName"] = "edgestile_' . $addon . $json->code . '";' . "\r" .
			    '$CONFIG["HostName"] = "localhost";' . "\r" .
			    '$CONFIG["DBUserName"] = "edgestile_' . $addon . $json->code . '";' . "\r" .
			    '$CONFIG["DBPassword"] = "' . $pass . '";' . "\r" .
			    '$CONFIG["DBDsn"] = "mysql";' . "\r" .
			    '$CONFIG["DBSerial"] = "1000047253";' . "\r" .
			    '?>';
		    file_put_contents($conf_url, $txt);
		    break;
		}
	    }
	}
    }
}

