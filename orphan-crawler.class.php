<?php

include 'site-crawler.class.php';
include 'ftp-crawler.class.php';

class OrphanCrawler{
	private $_site;		// SiteCrawler object
	private $_ftp;		// FTPCrawler object
	
	// provides the option to create one or both of the Crawler objects in one call
	public function __construct($options = null){
		// check if anything has been passed with the constructor call
		if(!is_null($options) && is_array($options)){
			// loop through the array to create the appropriate objects
			foreach ($options as $key => $value) {
				switch ($key) {
					// for FTPCrawler, check if a starting directory has been given
					case 'ftp':
						if(isset($value['start'])){
							$this->ftp($value['server'], $value['user'], $value['password'], $value['start']);
						} else {
							$this->ftp($value['server'], $value['user'], $value['password']);
						}
						break;
					// for SiteCrawler, simply pass the URL
					case 'site':
						$this->site($value);
						break;
				}
			}
		// if nothing passed, make the object holders null
		} else {
			$this->_site = null;
			$this->_ftp = null;
		}
	}
	
	// creates a new SiteCrawler object
	// is public so that the constructor can be empty, and to make it easy to re-use OrphanCrawler objects
	public function site($_start){
		$this->_site = new SiteCrawler($_start);
	}
	
	// creates a new FTPCrawler object
	// is public so that the constructor can be empty, and to make it easy to re-use OrphanCrawler objects
	public function ftp($_server, $_user, $_password, $_start = '/www'){
		$this->_ftp = new FTPCrawler($_server, $_user, $_password, $_start);
	}
	
	// takes an array, calls the settings function of the appropriate object(s)
	public function settings($options = null){
		// if nothing passed, loop through array
		if (!is_null($options)) {
			foreach ($options as $key => $value) {
				// switch between the array values to decide which object to access
				switch ($key) {
					case 'ftp':
						$return['ftp'] = $this->_ftp->settings($value);
						break;
					case 'site':
						$return['site'] = $this->_site->settings($value);
						break;
				}
			}
		// if nothing passed, get all visible settings from both objects
		} else {
			if(!is_null($this->_ftp)){
				$return['ftp'] = $this->_ftp->settings('get');
			}
			if(!is_null($this->_site)){
				$return['site'] = $this->_site->settings('get');
			}
		}
		return $return;
	}
	
	// compare the output of the two objects
	private function compare($ftp, $site, $type){
		switch ($type) {
			case 'php':
				foreach ($site as $key => $value) {
					$temp = explode('?', $value);
					if(count($temp) > 1){
						$site[$key] = $temp[0];
					}
					if(intval($value) !== 0){
						array_splice($site, $key, 1);
					}
				}
				foreach ($ftp as $key => $value) {
					if(intval($value) !== 0){
						array_splice($ftp, $key, 1);
					}
					$ftp[$key] = preg_replace('%(.*/)index\..*%', "$1", $value);
				}
				$site = array_unique($site);
				$site = array_values($site);
				$ftp = array_unique($ftp);
				$ftp = array_values($ftp);
				$return = array_diff($ftp, $site);
				break;
		}
		return $return;
	}
	
	public function output($what, $how = 'php'){
		switch ($what) {
			case 'site':
				$return['site'] = $this->_site->output($how);
				break;
			case 'ftp':
				$return['ftp'] = $this->_ftp->output($how);
				break;
			case 'compare':
				$ftp_temp = $this->_ftp->output('php');
				$ftp_temp = $ftp_temp['list'];
				array_pop($ftp_temp);
				
				$site_temp = $this->_site->output('php');
				$site_temp = $site_temp['crawl']['links'];
				
				$comp_temp = $this->compare($ftp_temp, $site_temp, $how);
				array_unshift($comp_temp, count($comp_temp));
				
				$return['orphans'] = $comp_temp;
				$return['ftp'] = $ftp_temp;
				$return['site'] = $site_temp;
				break;
		}
		return $return;
	}
}

?>