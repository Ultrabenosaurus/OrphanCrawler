<?php

class FTPCrawler{
	private $server;		// (string) host address of FTP server
	private $user;			// (string) username for logging into FTP server
	private $password;		// (string) password for logging into FTP server
	private $start_dir;		// (string) directory to treat as root [default: www]
	private $port;			// (integer) port to connect to [default: 21]
	private $passive;		// (boolean) whether or not a passive connection is required
	private $list;			// (array) the list of files found on the server
	private $dirs;			// (array) queue of all directories yet to be crawled
	private $file_types;	// (array) whitelist of files to listed
	private $ignore_dirs;	// (array|null) blacklist of directories [currently applies to any depth]
	private $ftp_conn;		// (resource) FTP connection pointer
	
	// sets initial values of variables
	public function __construct($_server, $_user, $_password, $_start = '/www'){
		// essential variables, bare minimum for script to work
		$this->server = $_server;
		$this->user = $_user;
		$this->password = $_password;
		$this->start_dir = $_start;
		
		// optional connection modifiers
		$this->port = 21;
		$this->passive = false;
		
		// crawling settings
		$this->list = array();
		$this->dirs = array($_start);
		$this->file_types = array('html', 'htm', 'php');
		$this->ignore_dirs = array();
		$ftp_conn = null;
	}
	
	// allows user to change variables if values are valid
	public function settings($options = 'get'){
		// ensure settings are given
		if(is_array($options) && count($options) > 0){
			// loop through all settings given
			foreach ($options as $setting => $value) {
				// check for existence of desired variable
				if(property_exists('FTPCrawler', $setting)){
					// switch through all variables for validation
					switch ($setting) {
						// check if desired value is a string
						case 'server':
						case 'user':
						case 'password':
						case 'start_dir':
							if (gettype($value) === 'string') {
								// set variable, return true
								$this->{$setting} = $value;
								$return[$setting] = true;
							} else {
								// return errors 
								$return[$setting] = array('value' => $value, 'error' => 'Given value was type '.gettype($value).', <em>string</em> required');
							}
							break;
						// check if value is an integer
						case 'port':
							if(gettype($value) === 'integer' || intval($value) !== 0){
								// set variable, return true
								$this->{$setting} = $value;
								$return[$setting] = true;
							} else {
								// return errors
								$return[$setting] = array('value' => $value, 'error' => 'Given value was type '.gettype($value).', <em>integer</em> required');
							}
							break;
						// check if value is boolean
						case 'passive':
							if(gettype($value) === 'boolean'){
								// set variable, return true
								$this->{$setting} = $value;
								$return[$setting] = true;
							} else {
								// return errors
								$return[$setting] = array('value' => $value, 'error' => 'Given value was type '.gettype($value).', <em>boolean</em> required');
							}
							break;
						// check if value is populated array
						case 'file_types':
							if(gettype($value) === 'array' && count($value) > 0){
								// loop through array
								foreach ($value as $type) {
									// check if value is a string
									if(gettype($type) === 'string'){
										// set variable, return true
										$this->{$setting}[] = $type;
										$return[$setting][$type] = true;
									} else {
										// return errors if not string
										$return[$setting][$type] = array('type' => $type, 'error' => 'Given value was type '.gettype($value).', <em>string</em> required');
									}
								}
								// remove duplicates from array
								$this->{$setting} = array_unique($this->{$setting});
							} else {
								// return errors if not array
								$return[$setting] = array('value' => $value, 'error' => 'Given $value was type '.gettype($value).', <em>array</em> required');
							}
							break;
						// check if value is populated array
						case 'ignore_dirs':
							if(gettype($value) === 'array' && count($value) > 0){
								// loop through array
								foreach ($value as $type) {
									// check if value is a string
									if(gettype($type) === 'string'){
										// set variable, return true
										$this->{$setting}[] = $type;
										$return[$setting][$type] = true;
									} else {
										// return errors if not string
										$return[$setting][$type] = array('type' => $type, 'error' => 'Given $value was type '.gettype($value).', <em>string</em> required');
									}
								}
								// remove duplicates from array
								$this->{$setting} = array_unique($this->{$setting});
							// check if value is null
							} elseif(is_null($value)){
								// set variable, return true
								$this->{$setting} = $value;
								$return[$setting] = true;
							} else {
								// return errors if not array or null
								$return[$setting] = array('setting' => $setting, 'value' => $value, 'error' => 'Given $value was type '.gettype($value).', <em>array</em> or <em>null</em> required');
							}
							break;
					}
				}
			}
		} else {
			// if $options is not an array, switch it to decide what to do
			switch ($options) {
				case 'get':
				// if $options is 'get', print all variables but $dirs (usually empty) and $list (could be massive)
					// initiate a ReflectionClass object, gather properties
					$reflect = new ReflectionClass($this);
					$properties = $reflect->getProperties();
					// loop through properties, add to array if not $list or $dirs
					foreach ($properties as $prop) {
						$name = $prop->getName();
						if($name !== 'list' && $name !== 'dirs'){
							$return[$name] = $this->{$name};
						}
					}
					break;
			}
		}
		// return results
		return $return;
	}
	
	// closes connection, destroys variables
	public function __destruct(){
		// close connection
		$disconn = $this->disconnect();
		// unset all variables
		foreach (get_object_vars($this) as $key => $value) {
			unset($this->{$key});
		}
	}
	
	// attempts connection and login to FTP server
	public function connect(){
		// try to connect to FTP server
		$this->ftp_conn = ftp_connect($this->server, $this->port);
		// if cannot login, return errors
		if(!ftp_login($this->ftp_conn, $this->user, $this->password)){
			$return = array('details' => array('server' => $this->server, 'port' => $this->port, 'username' => $this->user, 'password' => $this->password, 'passive' => $this->passive), 'connected' => is_resource($this->ftp_conn), 'logged_in' => false);
		} else {
			// check if passive mode is required
			if($this->passive){
				// if cannot enable passive mode return errors
				if(!ftp_pasv($this->ftp_conn, true)){
					$return = array('details' => array('server' => $this->server, 'port' => $this->port, 'username' => $this->user, 'password' => $this->password, 'passive' => $this->passive), 'connected' => is_resource($this->ftp_conn), 'logged_in' => true, 'other' => 'Could not turn <em>passive mode</em> on.');
				// otherwise try to move to starting directory
				} else {
					// otherwise return true
					//ftp_chdir($this->ftp_conn, $this->start_dir);
					$return = true;
				}
			// otherwise try to move to starting directory
			} else {
				// otherwise return true
				//ftp_chdir($this->ftp_conn, $this->start_dir);
				$return = true;
			}
		}
	// return results
	return $return;
	}
	
	private function crawl(){
		// make a copy of $dirs to keep track of changes, loop through it
		$temp_dirs = $this->dirs;
		foreach ($temp_dirs as $dir) {
			// change dir, list everything there
			ftp_chdir($this->ftp_conn, $dir);
			$temp_list = ftp_nlist($this->ftp_conn, ftp_pwd($this->ftp_conn));
			// loop through directory listing, check if file or directory
			foreach ($temp_list as $path) {
				if($this->ftpIsDir($path)){
					// if directory, check if it should be ignored
					if(!empty($this->ignore_dirs)){
						$temp_path = explode('/', $path);
						$add = true;
						// loop through directory blacklist
						foreach ($this->ignore_dirs as $key => $value) {
							// if directory should be ignored, don't add it
							if(array_search($value, $temp_path) === true){
								$add = false;
							}
						}
						// add directory to $dirs if not in blacklist
						if($add){
							$this->dirs[] = $path;
						}
					// if no blacklisted directories, add all to $dirs
					} else {
						$this->dirs[] = $path;
					}
				} else {
					// if a file, check the filetype against the whitelist
					$filetype = explode('.', $path);
					$filetype = $filetype[count($filetype)-1];
					if(array_search($filetype, $this->file_types) !== false){
						// if on whitelist, remove the leading /www and add to $list
						$this->list[] = $this->pathFix($path);
					}
				}
			}
			// remove current directory from $dirs, replace copy with the new list
			array_shift($this->dirs);
			$temp_dirs = $this->dirs;
		}
		// until $dirs is empty, keep crawling
		if(count($this->dirs) > 0){
			$this->crawl();
		}
		// although unlikely, make sure there are no duplicates
		$this->arrayShuffle();
	}
	
	// sorts arrays
	private function arrayShuffle(){
		// remove duplicate values
		$this->list = array_unique($this->list);
		// sort alphabetically
		sort($this->list);
		// reset numeric keys
		$this->list = array_values($this->list);
	}
	
	// removes the leading directory (eg: /www) so the reported paths can be used as links easily
	private function pathFix($dir){
		// split the given path on each slash
		$dirname = explode('/', $dir);
		// get rid of the first two items
		array_shift($dirname);
		array_shift($dirname);
		// put it back together
		$dirname = '/'.implode('/', $dirname);
		return $dirname;
	}
	
	// attempt disconnect from server
	public function disconnect(){
		// if a connection is established, try to close it
		if(isset($this->ftp_conn) && is_resource($this->ftp_conn)){
			// if cannot close connection return errors
			if(!ftp_close($this->ftp_conn)){
				$return = array('details' => array('server' => $this->server, 'port' => $this->port, 'username' => $this->user, 'password' => $this->password, 'passive' => $this->passive), 'connected' => is_resource($this->ftp_conn), 'logged_in' => true, 'other' => 'Could not terminate FTP connection.');
			// otherwise return true
			} else {
				$return = true;
			}
		// if no open connection return errors
		} else {
			$return = array('details' => array('server' => $this->server, 'port' => $this->port, 'username' => $this->user, 'password' => $this->password, 'passive' => $this->passive), 'connected' => is_resource($this->ftp_conn), 'logged_in' => false, 'other' => 'No FTP connection available to be terminated.');
		}
		// return results
		return $return;
	}
	
	// used to toggle passive mode without using the settings() method
	public function passiveToggle(){
		// invert current setting for $passive, set error text
		if($this->passive){
			$this->passive = false;
			$toggle = 'off';
		} else {
			$this->passive = true;
			$toggle = 'on';
		}
		// if cannot change passive status return errors
		if(!ftp_pasv($this->ftp_conn, $this->passive)){
			$return = array('details' => array('server' => $this->server, 'port' => $this->port, 'username' => $this->user, 'password' => $this->password, 'passive' => $this->passive), 'connected' => is_resource($this->ftp_conn), 'logged_in' => true, 'other' => 'Could not turn <em>passive mode</em> '.$toggle.'.');
		// otherwise return true
		} else {
			$return = true;
		}
		// return results
		return $return;
	}
	
	// check if the desired path is a directory or file
	private function ftpIsDir($dir){
		// try to move directory to see if if works
		if(@ftp_chdir($this->ftp_conn, $dir)){
			// if it works, go back one and return true
			ftp_chdir($this->ftp_conn, '..');
			return true;
		// otherwise return false
		} else {
			return false;
		}
	}
	
	// formats data for output to user
	public function output($type = 'xml'){
		// gather data by crawling the server
		$this->crawl();
		switch ($type) {
			case 'xml':
				break;
			case 'php':
			// for php format, collect data into a multi-dimensional array
				$return['details']['server'] = $this->server;
				$return['details']['port'] = $this->port;
				$return['details']['username'] = $this->user;
				$return['details']['password'] = $this->password;
				if($this->passive){
					$return['details']['passive'] = 'true';
				}
				$return['details']['start_dir'] = $this->start_dir;
				$return['list:'.count($this->list)] = $this->list;
				break;
		}
		// return formatted data
		return $return;
	}
}

?>