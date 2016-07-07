<?php

$url = parse_url('http://neptun5.beget.ru');
     foreach(gethostbynamel($url['host']) as $ip) {
             echo $ip_host = $ip;
}
echo $forward_ip = $_SERVER['HTTP_X_REAL_IP'];


