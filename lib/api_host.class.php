<?php
//error_reporting(E_ALL);
class apiHost {
	private $serial;
	private $templatedir = '/admin/home/siteedit/update/templates';
	private $url = 'https://api.beget.ru/api';
	private $hostname = '';
	private $root = '';
	private $dirSetup = '';
	private $db;
	private $pref = 'edgestile_';
	private $suffix = '';
	private $dump_version;
	private $host_id = 0;
	private $tmpname = 'se-24.com';
	private $site_id = 0;
	private $dbname = '';
	private $hostver = '/business/5.2';
	private $isBase = true;


	public function __construct($serial, $hostname = '', $hostver = '/multi/6.0', $dump_version = 'business', $lang = ''){
	 
	    $this->serial = $serial;	
	    $this->db = mysqli_connect(rHostName, rDBUserName, rDBPassword, rDBUserName) or die("mysql_error");
	    mysqli_query($this->db, "set character_set_client='UTF8'");
	    mysqli_query($this->db, "set character_set_results='UTF8'");
	    mysqli_query($this->db, "set collation_connection='utf8_general_ci'");
		
		$query = mysqli_query($this->db, "SELECT * FROM `api_hosts` WHERE `serial`='{$serial}';");
		if ($query->num_rows == 0) {
			if (!$hostname) $hostname = $serial.'.'.$this->tmpname;
			mysqli_query($this->db, "INSERT INTO `api_hosts`(`serial`,`hostname`) VALUES ('{$serial}','{$hostname}');");
			echo mysqli_error($this->db);
		} else {
			$line = mysqli_fetch_assoc($query);
			$this->host_id = $line['id_api'];
			$hostname = $line['hostname'];
			$this->dbname = $line['dbname'];
		}
		$this->hostname = $hostname;
		$this->hostver = $hostver;
		$this->dump_version = $dump_version;
		$this->root = get_account_path($this->hostname);
	    $this->suffix = substr($serial, 0, 1).substr($serial, 5, 5);
	    $this->dirSetup = SE_SETUP . $this->hostver;
	    if (!is_dir($this->root.'/public_html') || !$this->host_id){
	        $this->host_id =$this->setHost();
	    }
	    if (!file_exists($this->root.'/public_html/system/config_db.php') && $this->host_id){
	        $this->setDb();
	    }
	}

	public function getHostName(){
		return $this->hostname;
	}

	public function getJson($command, $data = array()) {
		$data_json = json_encode($data);
		$param = '?login=' . API_login . '&passwd=' . API_password . 
		   '&input_format=json&output_format=json'; 
		//echo file_get_contents($this->url.$command.$param .'&input_data=' . urlencode($data_json));   
		return json_decode(file_get_contents($this->url.$command.$param .'&input_data=' . urlencode($data_json)), true);
	}

	public function setDb(){
		if ($this->isBase && (!$this->dbname || !file_exists($this->root . "/public_html/system/config_db.php"))){
		    $userdbpassword = substr(md5($this->suffix.time()), 0, 10);
	        $dbname = $this->pref. $this->suffix;
	        $result = $this->getJson('/mysql/addDb', array("suffix"=>$this->suffix, "password"=>$userdbpassword));
			if ($result['answer']['status'] == 'error') {
				$result = $this->getJson('/mysql/changeAccessPassword', 
				array("suffix"=>$this->suffix,"access"=>"localhost","password"=>$userdbpassword));
			} 
	        $f = fopen($this->root . "/public_html/system/config_db.php", "w");
	        if (flock($f, LOCK_EX)) {
	            fputs($f, '<?php
$CONFIG["DBName"] = "' . $dbname . '";
$CONFIG["HostName"] = "localhost";
$CONFIG["DBUserName"] = "' . $dbname . '";
$CONFIG["DBPassword"] = "' . $userdbpassword . '";
$CONFIG["DBDsn"] = "mysql";
$CONFIG["DBSerial"] = "' . $serial . '";
?>');
	            flock($f, LOCK_UN); // отпираем файл
	        }
	        fclose($f);

	        sleep(1);
			$result = $this->getJson('/mysql/changeAccessPassword', 
					array(	"suffix"=>$this->suffix, 
							"access"=>"localhost",
							"password"=>$userdbpassword));
	        if ($result['answer']['status'] == 'success') {
	            mysqli_query($this->db, "UPDATE `api_hosts` SET `dbname`='{$dbname}' WHERE `serial` = '{$this->serial}'");
				$this->dbname = $dbname;
		        $db = mysqli_connect('localhost', $dbname, $userdbpassword, $dbname) or die('Error connecting to MySQL server: ' . mysqli_error());
		        $this->setSQLTemplate($db, $this->dump_version);
		        if ($lang) {
		           $this->setSQLTemplate($db, $this->dump_version . '/data_'. $lang);
		        }
		    }
										

        }
	}

	private function setSQLTemplate($db, $version = 'business')
	{
	    if (!file_exists(ROOT . '/admin/home/siteedit/bin/dumps/' . $version . '.sql')) return;
	     mysqli_query($db, "set character_set_client='UTF8'");
	     mysqli_query($db, "set character_set_results='UTF8'");
	     mysqli_query($db, "set collation_connection='utf8_general_ci'");
	    $templine = '';
	    $lines = file(ROOT . '/admin/home/siteedit/bin/dumps/' . $version . '.sql');
	    foreach ($lines as $line) {
	        if (substr($line, 0, 2) == '--' || $line == '') {
	            continue;
	        }
	        $templine .= $line;
	        if (substr(trim($line), -1, 1) == ';') {
	            mysqli_query($db, $templine);// or print('Error performing query \'<strong>' . $templine . '\': ' . mysqli_error() . '<br /><br />');
	            $templine = '';
	        }
	        
	    }
	    return true;
	}

    public function openTable()
    {
    	if ($this->root && file_exists($this->root.'/public_html/system/config_db.php')){
            include $this->root.'/public_html/system/config_db.php';
            return $CONFIG;
    	} else {
    	    $this->setDb();
            include $this->root.'/public_html/system/config_db.php';
            return $CONFIG;
    	}
    }

    public function setHost(){
        if (!$this->host_id && $this->hostname) {
            $result = $this->getJson('/site/add', array("name"=>$this->hostname));
            if ($result['answer']['status'] == 'error'){
                $result = $this->getJson('/site/getList', array());
                if ($result['answer']['status'] == 'success'){
                    foreach($result['answer']['result'] as $st){
                        if ($st['path'] == $this->hostname . '/public_html') {
                            $this->host_id = $st['id'];
                            break;
                        }
                    }
                }
            } elseif ($result['answer']['status'] == 'success'){
                $this->host_id = $result['answer']['result'];
            }
            if (!$this->host_id) return -1;
            mysqli_query($this->db, "UPDATE `api_hosts` SET `id_api`='{$this->host_id}' WHERE `serial` = '{$this->serial}'");
            $this->addDomain($this->hostname);
        }

        if (is_dir($this->root . '/public_html') && !is_dir($this->root . '/public_html/system')){
            unlink($this->root . '/public_html/index.php');
            rmdir($this->root . '/public_html/cgi-bin');
            $this->DirCopy($this->dirSetup, $this->root);
        }
        if ($this->host_id) {
            $this->setDb();
        }
        return $this->host_id;
    }

    private function getZones() {
		if (!file_exists(dirname(__FILE__).'/data/globzones.json')){
			$result = getJson('/domain/getZoneList', array());
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

	public function getZoneId($alias) {
		$globalzones = $this->getZones();
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

    // Привязывает с проверкой
	public function addDomain($alias, $site_id = '') {
		if (!$site_id) $site_id = $this->host_id;
		if (!$site_id) return -20; // Отсутствует хост		
		$id_domain = $id_host = 0;
		$aliaszone = explode('.', $alias);
		unset($aliaszone[0]);
		$aliaszone = implode('.', $aliaszone);
		$res = array();
		
		$req = mysqli_query($this->db, "SELECT id, id_host FROM api_domains WHERE id_domain IS NULL AND fqdn='{$alias}'");
		if (!empty($req)) {
			list($id_domain, $id_host) = mysqli_fetch_row($req);
			if ($id_host && $id_host != $site_id) return -10;  // Домен прилинкован к другому сайту
		}
		if (!$id_domain) {
			$zone = $this->getZoneId($alias);
			$result = $this->getJson('/domain/addVirtual', array('hostname'=>str_replace('.' . $aliaszone, '', $alias), 'zone_id'=>$zone));
		   	if ($result['answer']['status'] == 'error'){
				$result = $this->getJson('/domain/getList', array());
				if ($result['answer']['status'] == 'success'){
					foreach($result['answer']['result'] as $dm){
						if ($dm['fqdn'] == $alias) {
							$id_domain = $dm['id'];	
							break;
						}
					}
					
				}
		   	} else {
		   		$id_domain = $result['answer']['result'];
		   	}
			if ($id_domain)
				mysqli_query($this->db, "INSERT INTO api_domains(`id`, `id_host`, `fqdn`) VALUES('{$id_domain}', '{$site_id}', '{$alias}')");
		}
		if (!$id_domain) return false;
		   	sleep(1);
			$result = $this->getJson('/site/linkDomain', array('domain_id'=>$id_domain, 'site_id'=>$site_id));
		if ($result) {
			mysqli_query($this->db, "UPDATE api_domains SET `id_host`={$site_id} WHERE id={$id_domain}");
		}
		return $id_domain;
	}

	public function addSubDomain($subdomain, $host_id = '') {
		if (!$host_id) $host_id = $this->host_id;
		if (!$host_id) return -20;

		$aliaszone = explode('.', $subdomain);
		$subname = $aliaszone[0];
		$domain = str_replace($aliaszone[0].'.', '', $subdomain);
		$req = mysqli_query($this->db, "SELECT id, id_domain FROM api_domains WHERE id_domain>0 AND fqdn='{$subdomain}'");
		if (!empty($req)) {
			list($id_subdomain, $id_domain) = mysqli_fetch_row($req);
		}
		if (!$id_subdomain || !$id_domain) {

	        // Ищем в базе домен к которому создается субдомен
			$req = mysqli_query($this->db, "SELECT id, id_host FROM api_domains WHERE id_domain IS NULL AND fqdn='{$domain}'");
			if (!empty($req)) {
				list($id_domain, $id_host) = mysqli_fetch_row($req);
			}
			if (empty($id_domain)) return -1;  // Домен не найден
					
			sleep(1);
			$result = $this->getJson('/domain/addSubdomainVirtual', array("subdomain"=>$subname, "domain_id"=> $id_domain));
			// Если поддомен уже ранее создан
			if ($result['answer']['status'] == 'error') {
				$result = $this->getJson('/domain/getSubdomainList', array());
				if ($result['answer']['status'] == 'success') {
					foreach($result['answer']['result'] as $dm){
						if ($dm['fqdn'] == $subdomain) {
							$id_subdomain = $dm['id'];	
							break;
						}
					}
				}
				
			} else {
				$id_subdomain = $result['answer']['result'];
			}
			if (empty($id_subdomain)) return -2;  // Субдомен не найден
			sleep(1);
			if ($this->host_id)
				$result = $this->getJson('/site/linkDomain', array('domain_id'=>$id_subdomain, 'site_id'=>$this->host_id));
				if (!empty($result['answer']['result'])){
echo "ddd";
					mysqli_query($this->db, "INSERT INTO api_domains(`id`, `id_host`, `id_domain`,`fqdn`) VALUES('{$id_subdomain}', '{$this->host_id}', '{$id_domain}', '{$subdomain}')");
					echo mysqli_error($this->db);
				}
		}
			return $id_domain;
	}


	public function deleteHost(){
		if ($this->host_id) {
			$this->deleteDb();
			$this->deleteDomain($this->hostname);
			$result = $this->getJson('/site/delete', array("id"=>$this->host_id));
		    if ($result['answer']['status'] == 'success'){
		    $this->host_id = 0;
	            mysqli_query($this->db, "UPDATE `api_hosts` SET `id_api`='{$this->host_id}' WHERE `serial` = '{$this->serial}'");
				return true;
		    }
		}
	}

	public function deleteDb()
	{
	    if ($this->suffix) {
	        $result = $this->getJson('/mysql/dropDb', array(	"suffix"=>$this->suffix));
	        if ($result['answer']['status'] == 'success'){
            	mysqli_query($this->db, "UPDATE `api_hosts` SET `dbname`='' WHERE `serial` = '{$this->serial}'");
	            return true;
	        }
	    }
	}

	public function deleteDomain($domainName){
		if ($this->host_id) {
			$req = mysqli_query($this->db, "SELECT id, id_host FROM api_domains WHERE fqdn='{$domainName}'");
			if (!empty($req)) {
				list($id_domain, $id_host) = mysqli_fetch_row($req);
				if ($id_domain) {
					$result = $this->getJson('/site/unlinkDomain', array('domain_id'=>$id_domain));
					if ($result['answer']['result']){
						$result = $this->getJson('/domain/delete', array("id"=>$id_domain));
			    		if ($result['answer']['status'] == 'success'){
		            		mysqli_query($this->db, "DELETE FROM `api_domains` WHERE id={$id_domain}");
							return true;
						}
					}	
				}
			}
		}
	}

	public function deleteSubDomain($domainName){
		$req = mysqli_query($this->db, "SELECT id FROM api_domains WHERE fqdn='{$domainName}'");
		if (!empty($req)) {
			list($id_domain) = mysqli_fetch_row($req);
			if ($id_domain) {
				$result = $this->getJson('/site/unlinkDomain', array('domain_id'=>$id_domain));
				if ($result['answer']['result']){
					$result = $this->getJson('/domain/deleteSubdomain', array("id"=>$id_domain));
	    			if ($result['answer']['status'] == 'success'){
            			mysqli_query($this->db, "DELETE FROM `api_domains` WHERE id={$id_domain}");
						return true;
					}
				}	
			}
		}
	}





	private function ClearDir($dir) {
	    chdir($dir);
	    $d = opendir(".");
	
	    while (($f = readdir($d)) !== false) {
	        if ($f == '.' || $f == '..' || !is_file($f))
	            continue;
	        if (($f != 'index.php') && ($f != '.htaccess') && ($f != 'favicon.ico') && ($f != 'robots.txt') && (!is_link($f)))
	            unlink($f);
	    }
	    closedir($d);
	    return;
	}

	private function DirCopy($dirFrom, $dirTo) {
	    chdir($dirFrom);
	    $d = opendir('.');
	    while (($f = readdir($d)) !== false) {
	        if ($f == '.' || $f == '..')
	            continue;
	        if (is_link($f) && !is_link($dirTo . "/" . $f) && is_dir($dirTo . "/" . $f)) {
	            $this->ClearDir($dirTo . "/" . $f . '/', true);
	        }
	        if (is_link($dirTo . "/" . $f) || file_exists($dirTo . "/" . $f))
	            @unlink($dirTo . "/" . $f);
	        if (is_link($f)) {
	            symlink(readlink($f), $dirTo . "/" . $f);
	            continue;
	        }
	        if (is_dir($f)) {
	            chdir($f);
	            $dirToNew = $dirTo . "/" . $f;
	            if (!(file_exists($dirToNew))) {
	                mkdir($dirToNew, 0777);
	            }
                $this->DirCopy($f, $dirToNew);
            } else {
                copy($f, $dirTo . "/" . $f);
            }
        }
        closedir($d);
        chdir("..");
        return;
    }


    public function AddTemplate($template, $namefolder, $domains = '', $shopname = '')
    {
        if (!$this->host_id) return -20;
        $fl = false;
        if (is_dir($this->templatedir . '/projects/'.$template) && !is_dir($this->root. '/projects/'.$namefolder)){
            if (!is_dir($this->root. '/projects')) mkdir($this->root. '/projects');
            if (!is_dir($this->root. '/projects/'.$namefolder)) mkdir($this->root. '/projects/'.$namefolder);
                $this->dirCopy($this->templatedir . '/projects/'.$template, $this->root. '/projects/'.$namefolder);
                $fl = true;
        }
        if (is_dir($this->templatedir . '/wwwdata/'.$template) && !is_dir($this->root. '/public_html/wwwdata/'.$namefolder)){
            if (!is_dir($this->root. '/public_html/wwwdata')) mkdir($this->root. '/public_html/wwwdata');
            if (!is_dir($this->root. '/public_html/wwwdata/'.$namefolder)) mkdir($this->root. '/public_html/wwwdata/'.$namefolder);
            $this->dirCopy($this->templatedir . '/wwwdata/'.$template, $this->root. '/public_html/wwwdata/'.$namefolder);
            $fl = true;
        }
        if ($fl){
            $hosts = array();
            if (file_exists($this->root. '/projects/hostname.dat')){
                $filelist = file($this->root. '/projects/hostname.dat');
                foreach($filelist as $it){
                    $it = explode("\t", trim($it));
                    if ($it[1] != trim($namefolder)){
                        $hosts[] = $it[0]."\t".$it[1];
                    }
                }
            }
            $hosts[] = $domains."\t".$namefolder;
            $fp = fopen($this->root. '/projects/hostname.dat', "w+");
            fwrite($fp, join("\r\n", $hosts));
            fclose($fp);
            if ($shopname && file_exists($this->root. '/projects/'.$namefolder.'/dump.sql')){
                $CONFIG = $this->openTable();
                $db = mysqli_connect($CONFIG["HostName"], $CONFIG["DBName"], $CONFIG["DBPassword"], $CONFIG["DBName"]) or die('Error connecting to MySQL server: ' . mysqli_error());
                $req = mysqli_query($db, "SELECT FIELD FROM `main` WHERE FIELD  = `folder`");
                $idMain = 0;
                if (!empty($req) && !mysqli_num_rows($req)){
                    mysqli_query($db, "ALTER TABLE `main` ADD `folder` VARCHAR(20) NOT NULL AFTER `id`, ADD INDEX (`folder`) ;");
                    mysqli_query($db, "`ALTER TABLE main DROP INDEX lang;");
                }
                @list($domain) = explode(';', $domains);
                if($req = mysqli_query($db, "SELECT id FROM `main` WHERE `folder`='$namefolder';")){
                    list($idMain) = mysqli_fetch_row($req);
                }
                if ($idMain) {
                    mysqli_query($db, "UPDATE `main` SET `shopname`='{$shopname}',`domain`='{$domain}' WHERE `id`='$idMain';");
                } else {
                    mysqli_query($db, "INSERT INTO `main` (`folder`, `shopname`,`domain`) VALUES('{$namefolder}', '{$shopname}', '{$domain}');");
                    if($req = mysqli_query($db, "SELECT id FROM `main` WHERE `folder`='$namefolder';")){
                        list($idMain) = mysqli_fetch_row($req);
                    }
                }
                // Загрузим дамп магазина
                if ($idMain && $this->setDump($idMain)){
                    // Если импортировани успешно, удаляем дамп
                    unlink($this->root. '/projects/'.$namefolder.'/dump.sql');
                }
            }
        }
        return true;
    }

    private function setDump($id_main){
        return true;
    }
}
