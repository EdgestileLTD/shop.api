<?php

/**
 * Description of Abstract
 */
abstract class CurlAbstract {

    protected static $cookies;
//    protected static $me;
    const COOKIE_PATH = null;

    protected static $last_http_status;
    protected static $last_http_url;
    protected static $last_http_method;
    protected static $last_response_headers;
    protected static $last_response;
    protected static $last_sent_headers;
    protected static $last_sent_body;

    const RT_GET = 'GET';
    const RT_POST = 'POST';
    const RT_PUT = 'PUT';
    const RT_DELETE = 'DELETE';
    const RT_JSPOST = 'JSON_POST';
    const RT_JSPUT = 'JSON_PUT';
    const RT_JSDELETE = 'JSON_DELETE';

    public static function getCurl($url) {
        $curl = curl_init($url);
        self::$last_http_url = $url;
        if (self::COOKIE_PATH !== null) {
            if (!self::$cookies)
                    self::$cookies = tempnam(self::COOKIE_PATH, 'ZEN_CURL_COOKIE_');
            curl_setopt($curl, CURLOPT_COOKIEJAR, self::$cookies);
            curl_setopt($curl, CURLOPT_COOKIEFILE, self::$cookies);
        }
        curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        return $curl;
    }

    public static function query($url, $data = null, $method=null, $return = false, $headers = null) {
        if (!$method) {
            $method = 'GET';
        }
        self::$last_http_method = $method;
        $ch = false;
        if ($method == 'GET' && $data) {
            $data = http_build_query($data);
            $url .= '?'.$data;
            $ch = self::getCurl($url);
        } elseif ($method == 'GET') {
            $ch = self::getCurl($url);
        } elseif ($method == 'POST') {
            $ch = self::getCurl($url);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, @http_build_query($data));
            self::$last_sent_body = @http_build_query($data);
        } elseif(strpos($method, 'JSON_') !== false) {
            $ch = self::getCurl($url);
            $method = str_replace('JSON_', '', $method);
//            echo "json method: $method<br/>";
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            $data = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
//            curl_setopt($ch, CURLOPT_POSTFIELDSIZE, strlen($data));
        } elseif($method != 'GET') {
            $ch = self::getCurl($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($headers !== null) {
            if (is_array($headers)) {
                $rh = array();
                $hk = array_keys($headers);
                if (!is_numeric($hk[0])) {
                    $ch = count($hk);
                    for($i=0;$i<$ch;$i++) {
                        $k = $hk[i];
                        $rh[] = $k.': '.$headers[$k];
                    }
                    $headers = &$rh;
                }
            } else {
                $headers = array($headers);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($ch);
        if (curl_errno($ch)!== 0) {
            throw new Exception('CURL failed: '.curl_error($ch));
        }
        $ret = explode("\r\n\r\n", $ret);
        self::$last_response_headers = array_shift($ret);
		if (!empty($ret[1])) {
        	$ret = $ret[1];
		} else 
        $ret = implode("\r\n", $ret);

        self::$last_response = $ret;
        self::$last_http_status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        self::$last_sent_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
        curl_close($ch);
        if ($return)
            return $ret;
    }

    public static function queryJSON($url, $data = null, $method = null) {
        $body = self::query($url, $data, $method, true);

        $result = json_decode($body, true, 10);
        if (!$result) {
            throw new Exception('JSON FAILED: '.$body);
        }
        return $result;
    }

    public static function getJSON($url, $data = null, $method = null) {
        try {
            return self::queryJSON($url, $data, $method);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function get($url, $data = null, $method = null, $headers = null) {
        try {
            return self::query($url, $data, $method, true, $headers);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function debugLast() {
        echo '<hr/>';
        echo '<center><h1>Curl debug</h1></center>';
        echo '<hr/>';
        echo '<center><h2>'.self::$last_http_method.' Request</h2></center>';
        echo '<hr/>';
        echo nl2br(self::$last_sent_headers."\n\n".self::$last_sent_body);
        echo '<center><h2>Response: '.self::$last_http_status.'</h2></center>';
        echo '<hr/>';
        echo nl2br(self::$last_response_headers."\n\n".self::$last_response);
    }
}

?>