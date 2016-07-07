<?php
	//require_once dirname(__FILE__) . '/../../lib/function.php';

	function getZones() {
		if (!file_exists(dirname(__FILE__).'/data/globzones.json')){
			$result = json_decode(file_get_contents('https://api.beget.ru/api/domain/getZoneList?login=' . API_login . '&passwd=' . API_password . '&output_format=json'));
			if ($result->status == 'success') {
				$fs = fopen(dirname(__FILE__).'/data/globzones.json', "w+");
				fwrite($fs, json_encode($result));
				fclose($fs);
			}
		} else {
			$result = json_decode(join('', file(dirname(__FILE__).'/data/globzones.json')));
		}
		return $result;
	}

	function addDomain($alias, $zone, $site_id) {
		$aliaszone = explode('.', $alias);
		unset($aliaszone[0]);
		$aliaszone = implode('.', $aliaszone);
		$domainlist = getDomainList();
		$is_find = false;

		foreach($domainlist as $dd) {
		   if ($dd['fqdn'] == $alias) {
		      $is_find = true;
			  $domainid = $dd['id'];
			  break;
		   }
		}
		if (!$domainid) {
		   $result = json_decode(file_get_contents('https://api.beget.ru/api/domain/addVirtual?login=' . API_login . '&passwd=' . API_password . 
		      '&input_format=json&output_format=json&input_data=' . urlencode('{"hostname":"' . str_replace('.' . $aliaszone, '', $alias) . '","zone_id": ' . $zone . '}')), true);
		   sleep(1);
		   $domainid = $result['answer']['result'];
		}
		if (!$domainid) return;
		echo 'Пытаемся привязать домен | '.$alias.' domainid = ' . $domainid . "\n";
		$result = json_decode(file_get_contents('https://api.beget.ru/api/site/linkDomain?login=' . API_login . '&passwd=' . API_password . 
		   '&input_format=json&output_format=json&input_data=' . urlencode('{"domain_id":' . $domainid . ',"site_id":' . $site_id . '}')), true);
		sleep(1);
		if ($result) {
			// Добавим в список доменов
			if (!$is_find) {
			echo 'Дабавляем домен:'.$alias."\n";
			    $domainlist[] = array('id'=>$domainid, 'fqdn'=>$alias, 'date_add'=>date('Y-m-d H:i:s'));
			    $fs = fopen(dirname(__FILE__).'/data/domains.json', "w+");
			    fwrite($fs, json_encode($domainlist));
			    fclose($fs);
			}
			return array('id'=>$domainid , 'fqdn'=>$alias);
		} else {
		     echo "Домен ". $alias . " уже привязан\n";
		}
	}

	function addSubDomain($alias, $domain_id, $aliaszone) {
		//$aliaszone = explode('.', $alias);
		//unset($aliaszone[0]);
		//$aliaszone = implode('.', $aliaszone);
		$result = json_decode(file_get_contents('https://api.beget.ru/api/domain/addSubdomainVirtual?login=' . API_login . '&passwd=' . API_password .
		   '&input_format=json&output_format=json&input_data=' . urlencode('{"subdomain": "' . str_replace('.' . $aliaszone, '', $alias) . '","domain_id": ' . $domain_id . '}')), true);
		sleep(1);
		// Добавим в список субдоменов
		if ($result['status'] == 'success') {
			$id = $result['answer']['result'];
			// Добавим в список доменов
			$domainlist = getDomainList();
			$domainlist[] = array('id'=>$id, 'fqdn'=>$alias, 'domain_id'=>$domain_id);
			echo 'Дабавляем субдомен:'.$alias."\n";
			//print_r($result);
			$fs = fopen(dirname(__FILE__).'/data/subdomains.json', "w+");
			fwrite($fs, json_encode($domainlist));
			fclose($fs);
			return array('id'=>$id, 'fqdn'=>$alias);
		} else {
		    print_r($result);
		}
	}


	function getHostElements($update = false){
		if (!$update && file_exists(dirname(__FILE__).'/data/sites.json')){
			$domainlist = json_decode(join('', file(dirname(__FILE__).'/data/sites.json')), true);
		} else {
			$dlist = json_decode(file_get_contents('https://api.beget.ru/api/site/getList?login=' . API_login . '&passwd=' . API_password . '&output_format=json'), true);
			if ($dlist['status'] == 'success') {
			   $domainlist = $dlist['answer']['result'];
			   $fs = fopen(dirname(__FILE__).'/data/sites.json', "w+");
			   fwrite($fs, json_encode($domainlist));
			   fclose($fs);
			}
		}
		return $domainlist;
	}

	function findHostElement($domain) {
		$elements = getHostElements(); 
		foreach($elements as $dom) {
		   $path = str_replace('/public_html', '', $dom['path']);
		   if ($path == $domain) {
			  return $dom;
		   }
		}
	}

	function addHostElement($id, $domain, $domains = array()){
		$elements = getHostElements();
		$is_find = false;
		foreach($elements as $key=>$dom) {
		   if ($dom['id'] == $id) {
			  $elements[$key]['domains'] = $domains;
			  $is_find = true;
			  break;
		   }
		}
		if (!$is_find) {
	  		 echo "Добавляем хост ". $id. ' ' .$domain."\n";
			$elements[] = array('id'=>$id, 'path'=>$domain . '/public_html', 'domains'=>$domains);
		} 	
		$fs = fopen(dirname(__FILE__).'/data/sites.json', "w+");
		fwrite($fs, json_encode($elements));
		fclose($fs);
	}


	function deleteHostElement($id){
		$elements = getHostElements();
		$is_find = false;
		foreach($elements as $key=>$dom) {
		   if ($dom['id'] == $id) {
			  foreach($dom['domains'] as $line) {
				deleteDomainList($line['id']);
			  }
			  unset($elements[$key]);
			   $is_find = true;
		   }
		}
		if ($is_find) {
			$fs = fopen(dirname(__FILE__).'/data/sites.json', "w+");
			fwrite($fs, json_encode($elements));
			fclose($fs);
		}
	}

	// Domens
	function getDomainList($update = false) {
		if (!$update && file_exists(dirname(__FILE__).'/data/domains.json')){
			$domainlist = json_decode(join('',file(dirname(__FILE__).'/data/domains.json')), true);
		} else {
			$domainlist = json_decode(file_get_contents('https://api.beget.ru/api/domain/getList?login=' . API_login . '&passwd=' . API_password . '&output_format=json'), true);
			$fs = fopen(dirname(__FILE__).'/data/domains.json', "w+");
			fwrite($fs, json_encode($domainlist['answer']['result']));
			fclose($fs);
		}
		return $domainlist;
	}

	function deleteDomainList($id){
	        if (!$id) return;
		$elements = getDomainList();
		$is_find = false;
		echo 'Попытка удалить domain_id:' . $id."\n"; 
		foreach($elements as $key=>$dom) {
		   if ($dom['id'] == $id) {
			  sleep(2);
			  $result = json_decode(file_get_contents('https://api.beget.ru/api/domain/delete?login=' . API_login . '&passwd=' . API_password . 
				 '&input_format=json&output_format=json&input_data='. urlencode('{"id":'.$id.'}')), true);
			  sleep(2);
			//  print_r($result);
			  if ($result['answer']['status'] == 'success') {
		                 echo 'Удаляем домен:' . $dom['fqdn']."\n"; 
				unset($elements[$key]);
				$is_find = true;
				break;
			  }
		   }
		}
		if ($is_find) {
			$fs = fopen(dirname(__FILE__).'/data/domains.json', "w+");
			fwrite($fs, json_encode($elements));
			fclose($fs);
			return;
		}
		
		$is_find = false;
		$subelements = getSubDomainList();
		foreach($subelements as $key=>$dom) {
		   if ($dom['id'] == $id) {
		         // print_r($dom);
			  $result = json_decode(file_get_contents('https://api.beget.ru/api/domain/deleteSubdomain?login=' . API_login . '&passwd=' . API_password . 
				 '&input_format=json&output_format=json&input_data='. urlencode('{"id":'.$dom['id'].'}')), true);
			  sleep(3); 
			  //print_r($result);
			  if ($result['answer']['status']=='success') {
		                 echo 'Удаляем субдомен:' . $dom['fqdn']."\n"; 
				 unset($subelements[$key]);
				 $is_find = true;
			  }
		   }
		}
		if (!$is_find) {
			$fs = fopen(dirname(__FILE__).'/data/subdomains.json', "w+");
			fwrite($fs, json_encode($subelements));
			fclose($fs);
		}
	}

	// SubDomains
	function getSubDomainList($update = false) {
		if (!$update && file_exists(dirname(__FILE__).'/data/subdomains.json')){
			$domainlist = json_decode(join('',file(dirname(__FILE__).'/data/subdomains.json')), true);
		} else {
			$domainlist = json_decode(file_get_contents('https://api.beget.ru/api/domain/getSubdomainList?login=' . API_login . '&passwd=' . API_password . '&output_format=json'), true);
			$fs = fopen(dirname(__FILE__).'/data/subdomains.json', "w+");
			fwrite($fs, json_encode($domainlist['answer']['result']));
			fclose($fs);
		}
		return $domainlist;
	}

	function deleteSubDomainList($id){
		$elements = getSubDomainList();
		$is_find = false;
		foreach($elements as $key=>$dom) {
		   if ($dom['id'] == $id) {
			  $result = json_decode(file_get_contents('https://api.beget.ru/api/domain/deleteSubdomain?login=' . API_login . '&passwd=' . API_password . 
				 '&input_format=json&output_format=json&input_data='. urlencode('{"id":'.$dom['id'].'}')), true);
			  sleep(3);
			  if ($result['status']=='success') {
				 unset($elements[$key]);
				 $is_find = true;
			  }
		   }
		}
		if ($is_find) {
			$fs = fopen(dirname(__FILE__).'/data/subdomains.json', "w+");
			fwrite($fs, json_encode($elements));
			fclose($fs);
		}
	}



	function getZoneId($alias, $globalzones) {
		$zonefile = file(dirname(__FILE__).'/data/zones.dat');
		$zones = array();
		foreach($zonefile as $zone) {
		   list($zone, $id) = explode(':', $zone);
		   $zones[$zone] = $id;
		}
		$aliaszone = explode('.', $alias);
		unset($aliaszone[0]);
		$aliaszone = implode('.', $aliaszone);
		$id_zone = 0;
		if (!empty($zones[$aliaszone])) {
				$id_zone = $zones[$aliaszone];
		}
		if (!$id_zone)
			foreach ($globalzones->answer->result as $line) {
				if ($line->zone == $aliaszone) {
					$id_zone = $line->id;
					break;
				}
		}
		return $id_zone;
	}

	function setAliasZone($namesite, $site_id = 0, $aliases  = '') {
	        $namesite = (strpos($namesite, '.')===false) ? $namesite . '.e-stile.ru' : $namesite;

		$globalzones = getZones();
		// Читаем список 
		if (!$aliases) {
		    $db = mysql_connect(rHostName, rDBUserName, rDBPassword) or die("mysql_error");
		    mysql_select_db("edgestile_admin", $db);
		    mysql_query("set character_set_client='UTF8'", $db);
		    mysql_query("set character_set_results='UTF8'", $db);
		    mysql_query("set collation_connection='utf8_general_ci'", $db);
		    $query = mysql_query("SELECT domain, alias FROM `account` WHERE `domain` LIKE '" . str_replace('.e-stile.ru', '', $namesite) . "' OR `domain` LIKE '" . $namesite . "'", $db);
		    $answer = mysql_fetch_array($query);
		    //print_r($answer);
		    $aliases = $answer[1];
		}
		$zone_id = getZoneId($namesite, $globalzones);
		$domains = array();
		if ($site_id) {
		   echo 'Добавим домен: ' . $namesite . ' zone: '. $zone_id."\n";
		   $domains[] =  addDomain($namesite, $zone_id, $site_id);
		}
		//if ($aliases != '') {
			$aliaslist = explode(';', $aliases);
			foreach ($aliaslist as $key => $value) {
			   $aliaslist[$key] = trim($value);
			   if (empty($value)) {
				   unset($aliaslist[$key]);
			   }
			}
			$aliaslist[] = $namesite;


			//echo 'checking aliaseslist' . "\n";
			//check and unset domain that already exists
			$element = findHostElement($namesite);
 			$domainlist = $element['domains'];
 			$site_id = $element['id'];
                 

                    if (!empty($element['domains'])){
                        //print_r($element['domains']);
                        foreach($element['domains'] as $key=>$dm){
                            $find = false;
                            foreach($aliaslist as $fqdn){
                                if (trim($dm['fqdn']) == trim($fqdn) || trim($dm['fqdn']) == trim($namesite)){
                                    $find = true; 
                                    break;
                                }
                            }
                            if (!$find){
                                //echo 'delete:'.$dm['id']."\n";
                                deleteDomainList($dm['id']);
                                unset($element['domains'][$key]);
                            }
                        }
                    }

                    $domains = $element['domains'];
			foreach ($aliaslist as $key => $value) {
				foreach ($element['domains'] as $line) {
					if ($value == $line['fqdn']) {
						// это домен уже сдесь привязан, его удаляем из списка
						unset($aliaslist[$key]);
						break;
					}
				}
			}




			// Смотрим! есть ли он в субдоменах
			$subdomainlist = getSubDomainList();
			foreach ($aliaslist as $key => $value) {
				foreach ($subdomainlist as $line) {
					if ($value == $line['fqdn']) {
						unset($aliaslist[$key]);
						// это субдомен, его удаляем из списка
						break;
					}
				}
			}
//print_r($aliaslist);

			// Загрузим список доменов данного сервера
			if (!empty($aliaslist) && $site_id) {
				$domainlist = getDomainList();
				foreach ($aliaslist as $alias) {
					$alias_exists = false;
					foreach ($domainlist as $line) {
						if ($alias == $line['fqdn']) {
							$alias_exists = $line['id'];
							$domains[] =  array('id'=>$line['id'], 'fqdn'=>$line['fqdn']);
							break;
						}
					}
					
					
					if ($alias_exists) {
						// Привязываем найденный домен
						$result = json_decode(file_get_contents('https://api.beget.ru/api/site/linkDomain?login=' . API_login . '&passwd=' . API_password . 
							'&input_format=json&output_format=json&input_data=' . urlencode('{"domain_id":' . $alias_exists . ',"site_id":' . $site_id . '}')));
						sleep(1);

					} else {
						$zone_id = getZoneId($alias, $globalzones);
						if ($zone_id) {
							$domains[] =  addDomain($alias, $zone_id, $site_id);
						} else {
						        $aliaszone = explode('.', $alias);
						        array_splice($aliaszone, 0, 1);
						        $aliaszone = join('.', $aliaszone);
							   // Привязываем доддомен
							foreach ($domainlist as $line) {
								if ($aliaszone == $line['fqdn']) {
							            $domains[] =  addSubDomain($alias, $line['id'], $line['fqdn']);
								    break;
								}
							}
						}
					}
					// Привязываем доменное имя
				}
			}
		//}
		addHostElement($site_id, $namesite, $domains);
	}

	function create_host($namesite, $ver = '7', $mod = '5.1') {
		$prods = array('', 'oldfree', 'free', 'services', 'junior', 'startplus', 'standard', 'business', 'forum', 'partner', 'crm');
		$dirTo = get_account_path($namesite);
		$ver = ($ver > 10 && $ver < 100) ? $ver - 10 : $ver;
		$ver = ($ver < 100) ? $ver : 10;
		if (empty($prods[$ver]))
			return;
		if ($prods[$ver] < 100) {
		  $dirSetup = SE_SETUP . '/' . $prods[$ver] . '/' . $mod;
		} else {
		  $dirSetup = SE_SETUP . '/' . $prods[$ver];        
		}
		if (strpos($namesite, '.') === false) {
			$namesite .= SE_DOMAIN;
		}

		if (!is_dir($dirTo . '/public_html')) {
			// Создаем хост
			$answer = json_decode(file_get_contents('https://api.beget.ru/api/site/add?login=' . API_login . '&passwd=' . API_password . 
			   '&input_format=json&output_format=json&input_data=' . urlencode('{"name":"' . $namesite . '"}')), true);
			sleep(3);
			if ($answer['status'] == 'success'){
				 $site_id = $answer['answer']['result'];
				 addHostElement($site_id, $namesite);
				// echo $dirTo . ' -> ' . $dirSetup . "\n";
				 //chdir($dirTo);
				 if (is_dir($dirTo . '/public_html')){
				    ClearDir($dirTo . '/public_html');
				    chdir($dirSetup);
				    DirCopy($dirTo);
				    chdir(SE_ETC);
				 }
			}
			setAliasZone($namesite, $site_id);
		} else {
		    $element = findHostElement($namesite);
			setAliasZone($namesite, $element['id']);
		}
		return true;

	}

	function deleteHost($domain) {
		//mysql_connect(rHostName, rDBUserName, rDBPassword) or die("mysql_error");
		//mysql_select_db("edgestile_admin");
		//mysql_query("set character_set_client='UTF8'");
		//mysql_query("set character_set_results='UTF8'");
		//mysql_query("set collation_connection='utf8_general_ci'");

		$globalzones = getZones();

		// Читаем список 
		//$query = mysql_query("SELECT domain, alias FROM `account` WHERE `domain` LIKE '" . str_replace('.e-stile.ru', '', $domain) . "'");
		//$result = mysql_fetch_array($query);
		// Удаляем сайт
		$element = findHostElement($domain);
		$answer = json_decode(file_get_contents('https://api.beget.ru/api/site/delete?login=' . API_login . '&passwd=' . API_password . 
		  '&input_format=json&output_format=json&input_data=' . urlencode('{"id":"' . $element['id'] . '"}')), true);
		sleep(5);
		if ($answer['status'] == 'success') {
		   deleteHostElement($element['id']);
		   $dirTo = get_account_path($domain);
		   echo $dirTo.' ' . ROOT . '/admin/delete/'.$domain . "\n";
		   sleep(5);
		   rename($dirTo, ROOT . '/admin/delete/'.$domain . '_' . time());
		   return true;
		}
	}
//	deleteHost('mytest2015.e-stile.ru');
//    create_host('bacin.e-stile.ru');
