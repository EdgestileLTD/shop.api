<?php
    $SERVER_URL = 'http://neptun5.beget.ru';
        
    $forward_ip = $_SERVER['HTTP_X_REAL_IP'];
    $url = parse_url($SERVER_URL);
     foreach(gethostbynamel($url['host']) as $ip) {
          $ip_host = $ip;
     }

     if (!empty($_GET['token'])){
         $serial = substr($_GET['token'], 0, 10);
         $token = $_GET['token'];
         $folder = $_GET['folder'];
         $tokenDir = dirname(__FILE__).'/tokens';
         if (!is_dir($tokenDir)) 
            mkdir($tokenDir);
	     $tokenFile = $tokenDir.'/'.$serial.'.dat';
         $fp = fopen($tokenFile, "w+");
         fwrite($fp, $token.'|'.$folder);
         fclose($fp);
         echo 'OK';
         exit;
     } echo 'Not Authorize';
