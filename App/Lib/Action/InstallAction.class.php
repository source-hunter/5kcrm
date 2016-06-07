<?php 
class InstallAction extends Action {	
	
	public function _initialize(){
		if (!in_array(strtolower(ACTION_NAME), array('upgrade','upgradeprocess')) && file_exists(CONF_PATH . "install.lock")) {
			$this->error(L('PLEASE_DO_NOT_REPEAT_INSTALLATION'),U('index/index'));		
		}	
	}
	
	private $upgrade_site = "http://upgrade.5kcrm.com/";
	
	public function upgradeProcess() {
		$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
		$dir = getcwd() . "/Public/sql/";
		$upgrade_list = array();
		if(is_dir($dir)){  
			if( $dir_handle = opendir($dir) ){
				while (false !== ( $file_name = readdir($dir_handle)) ) {
					if($file_name=='.' or $file_name =='..'){
						continue;
					} elseif ($file_name != "5kcrm.sql" && strpos($file_name,'.sql')){
						$upgrade_list[] = $file_name;
					}
				}
			}
		}
		$db = M();
		foreach($upgrade_list as $upgrade){
			$sql .= file_get_contents($dir.$upgrade);
		}
        $sql = str_replace("5kcrm_", C('DB_PREFIX'), $sql); 
		$sql = str_replace("http://demo.5kcrm.com", __ROOT__, $sql);
		$sql = str_replace("\r\n", "\n", $sql); 
		$queries = explode(";\n", $sql); 
		
		$sum = sizeof($queries);
		if ($id < $sum) {
			if(trim($queries[$id])) { 
				$db->query($queries[$id]); 
			} 
		}
		$id++;
		if($id >= $sum){
			foreach($upgrade_list as $upgrade){
				@unlink($dir.$upgrade);
			}
		}
		$this->ajaxReturn($id, floor($id*100/$sum) . "%", 1);
	}
	public function upgrade() {
		$dir = getcwd() . "/Public/sql/";
		$upgrade_list = array();
		if(is_dir($dir)){  
			if( $dir_handle = opendir($dir) ){
				while (false !== ( $file_name = readdir($dir_handle)) ) {
					if($file_name=='.' or $file_name =='..'){
						continue;
					} elseif ($file_name != "5kcrm.sql" && strpos($file_name,'.sql')){
						$upgrade_list[] = $file_name;
					}
				}
			}
		}
		if (!empty($upgrade_list)) {
			sort($upgrade_list);
			if(file_exists(RUNTIME_FILE)){
				@unlink(RUNTIME_FILE);
			}
			$cachedir=RUNTIME_PATH."/Cache/";
			$cachefieldsdir=RUNTIME_PATH."/Data/_fields/";
			$cachetempdir=RUNTIME_PATH."/Temp/";
            if(is_dir($cachedir)){
				$cd = opendir($cachedir);
				while (($file = readdir($cd)) !== false) {
					if($file=='.' or $file =='..'){
						@unlink($cachedir.$file);
					}
				}
				closedir($cd);
			}
            
			if(is_dir($cachefieldsdir)){
				$cfd = opendir($cachefieldsdir);
				while (($file = readdir($cfd)) !== false) {
					if($file=='.' or $file =='..'){
						@unlink($cachefieldsdir.$file);
					}
				}
				closedir($cfd);
			}
            
            if(is_dir($cachefieldsdir)){
				$ctd = opendir($cachetempdir);
				while (($file = readdir($ctd)) !== false) {
					if($file=='.' or $file =='..'){
						@unlink($cachetempdir.$file);
					}
				}
				closedir($ctd);
            }
			
			$this->upgrade_list = $upgrade_list;
			$this->display();
		} else {
			$this->error(L('NO_CHECK_TO_UPGRADE_FILE'));	
		}			
	}
	public function checkVersion(){	
		$params = array('version'=>C('VERSION'), 'release'=>C('RELEASE'));
		$info = sendRequest($this->upgrade_site . 'index.php?m=index&a=checkVersion', $params);
		if ($info){
			$this->ajaxReturn($info);
		} else {
			$this->ajaxReturn(0, L('CHECK_THE_NEW_VERSION '), 0);
		}
	}

	public function index(){
		$this->display('step1');
	}	
	public function step2(){
		$data['os'] = PHP_OS;
		$data['php'] = phpversion();
		$this->envir_data = $data;
		$this->display();
	}
	public function step3(){
		$this->display();
	}
	public function step4(){
		if (file_exists(CONF_PATH . "install.lock")) {
			$this->error(L('PLEASE_DO_NOT_REPEAT_INSTALLATION'),U('Install/step3'));		
		}	
		if (!file_exists(getcwd() . "/Public/sql/5kcrm.sql")) {
			$this->error(L('LACK_THE_NECESSARY_DATABASE_FILES'),U('Install/step3'));		
		}
		if ($this->isPost()) {
			$db_config['DB_TYPE'] = 'mysql';
			$db_config['DB_HOST'] = $_POST['DB_HOST'];
			$db_config['DB_PORT'] = $_POST['DB_PORT'];
			$db_config['DB_NAME'] = $_POST['DB_NAME'];
			$db_config['DB_USER'] = $_POST['DB_USER'];
			$db_config['DB_PWD'] = $_POST['DB_PWD'];		
			$db_config['DB_PREFIX'] = $_POST['DB_PREFIX'];
			
			$name = $_POST['name'];
			$password = $_POST['password'];
			
			$warnings = array();
			if (empty($db_config['DB_HOST'])) {
				$this->error(L('PLEASE_FILL_IN_THE_DATABASE host'),U('Install/step3'));
			}			
			if (empty($db_config['DB_PORT'])) {
				$this->error(L('PLEASE_FILL_OUT_THE_DATABASE_PORT host'),U('Install/step3'));
			}
			if (preg_match('/[^0-9]/', $db_config['DB_PORT'])) {
				$this->error(L('DATABASE_PORT_ONLY_NUMBERS'),U('Install/step3'));
			}
			if (empty($db_config['DB_NAME'])) {
				$this->error(L('PLEASE_FILL_IN_THE_DATABASE_NAME'),U('Install/step3'));
			}
			if (empty($db_config['DB_USER'])) {
				$this->error(L('PLEASE_FILL_IN_THE_DATABASE_USER_NAME'),U('Install/step3'));
			}
			if (empty($db_config['DB_PREFIX'])) {
				$this->error(L('PLEASE_FILL_IN_THE_TABLE_PREFIX'),U('Install/step3'));
			}
			if (preg_match('/[^a-z0-9_]/i', $db_config['DB_PREFIX'])) {
				$this->error(L('THE_TABLE_PREFIX_CAN_CONTAIN_ONLY_NUMBERS_LETTERS_AND_UNDERSCORES'),U('Install/step3'));
			}
			if (empty($name)) {
				$this->error(L('PLEASE_FILL_IN_THE_ADMINISTRATOR_USER_NAME'),U('Install/step3'));
			}
			if (empty($password)) {
				$this->error(L('PLEASE_FILL_IN_THE_ADMINISTRATOR_PASSWORD'),U('Install/step3'));
			}

			if (empty($warnings)) {
				$connect = mysql_connect($db_config['DB_HOST'] . ":" . $db_config['DB_PORT'], $db_config['DB_USER'], $db_config['DB_PWD']);
				if(!$connect) {
					$this->error(L("THE_DATABASE_CONNECTION_FAILED_PLEASE_CHECK_THE_CONFIGURATION"),U('Install/step3'));
				} else {
					if(!mysql_select_db($db_config['DB_NAME'])) {
						if(!mysql_query("create database ".$db_config['DB_NAME']." DEFAULT CHARACTER SET utf8")) {
							$this->error(L("DO_NOT_FIND_YOU_FILL_OUT_THE_DATABASE_NAME_AND_CANNOT_BE_CREATED"),U('Install/step3'));
						}
					}
				} 
				if(!check_dir_iswritable(APP_PATH.'Runtime')){
					$this->error(L("RUNTIME_FOLDER_REQUIRES_WRITE_ACCES",array(APP_PATH)),U('Install/step3'));
				}
				if(!check_dir_iswritable(CONF_PATH)){
					$this->error(L("CONF_FOLDER_REQUIRES_WRITE_ACCES",array(CONF_PATH)),U('Install/step3'));
				}
			}
			if (empty($warnings)) {
				$db_config_str 	 = 	"<?php\r\n";
				$db_config_str	.=	"return array(\r\n";
				foreach($db_config as $k => $v) {
					$db_config_str .= "'" . $k."'=>'".$v."',\r\n";
					C($k,$v);
				}
				$db_config_str.=");";
				if(file_put_contents(CONF_PATH . "db.php", $db_config_str)){
					$db = M();
                    $sql = file_get_contents(getcwd() . "/Public/sql/5kcrm.sql");
                    $sql = str_replace("5kcrm_", C('DB_PREFIX'), $sql); 
                    $sql = str_replace("http://demo.5kcrm.com", __ROOT__, $sql);
					$sql = str_replace("\r\n", "", $sql); 
					$queries = explode(";\n", $sql); 
					foreach ($queries as $val) {
						if(trim($val)) { 
							$db->query($val); 
						} 
					}
					$salt = substr(md5(time()),0,4);
					$password = md5(md5(trim($password)) . $salt);
                    $db->query('insert into ' . C('DB_PREFIX') . 'user (role_id, category_id, status, name, password, salt, reg_ip, reg_time) values (1, 1, 1, "'.$name.'", "'.$password.'", "'.$salt.'", "'.get_client_ip().'", '.time().')'); 
					touch(CONF_PATH . "install.lock");
				}			
				$this->display('step4');
			}
		}else{
			$this->error('参数不正确',U('Install/step3'));
		}
	}
}