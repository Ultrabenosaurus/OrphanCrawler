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
	
	public function ftpDisconnect(){
		return $this->_ftp->disconnect();
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
	private function compare($ftp, $site){
		// remove query strings from SiteCrawler's results
		foreach ($site as $key => $value) {
			$temp = explode('?', $value);
			if(count($temp) > 1){
				$site[$key] = $temp[0];
			}
			$site[$key] = preg_replace('%(.*/)index\..*%', "$1", $value);
		}
		
		// remove filenames if they are just index pages
		foreach ($ftp as $key => $value) {
			$ftp[$key] = preg_replace('%(.*/)index\..*%', "$1", $value);
		}
		
		// remove duplicates and correct numeric keys
		$site = array_unique($site);
		$site = array_values($site);
		$ftp = array_unique($ftp);
		$ftp = array_values($ftp);
		
		// find all files on the server that are not linked to
		$return = array_diff($ftp, $site);
		$return = array_values($return);
		return $return;
	}
	
	// output crawl results to user (default: php)
	public function output($what, $how = 'php'){
		switch ($what) {
			case 'nocompare':
				// crawl the website for links
				$return['site'] = $this->_site->output($how);
				// crawl the server for files
				$return['ftp'] = $this->_ftp->output($how);
				break;
			case 'site':
				// crawl the website for links
				$return = $this->_site->output($how);
				break;
			case 'ftp':
				// crawl the server for files
				$return = $this->_ftp->output($how);
				break;
			case 'compare':
				// perform both crawls
				$ftp_temp = $this->_ftp->output('php');
				$site_temp = $this->_site->output('php');
				// compare the results of both crawls
				$comp_temp = $this->compare($ftp_temp['list'], $site_temp['crawl']['links']);
				$url = $this->settings();
				$url = $url['site']['url'];
				
				switch ($how) {
					case 'php':
						// organise all results into a multi-dimensional array
						$return['orphans']['total'] = count($comp_temp);
						$return['orphans']['list'] = $comp_temp;
						$return['ftp']['total'] = $ftp_temp['list_total'];
						$return['ftp']['list'] = $ftp_temp['list'];
						$return['site']['total'] = $site_temp['crawl']['links_total'];
						$return['site']['list'] = $site_temp['crawl']['links'];
						break;
					case 'xml':
						// new DOMDocument
						$xml_doc = new DOMDocument();
						$xml_doc->formatOutput = true;
						$root = $xml_doc->createElement('orphan_crawl');
						// orphans data
						$orphan_elem = $xml_doc->createElement('orphans');
						$orphan_elem->appendChild($xml_doc->createElement('total', count($comp_temp)));
						$orphan_list = $xml_doc->createElement('list');
						foreach ($comp_temp as $key => $value) {
							$orphan_item = $xml_doc->createElement('orphan');
							$orphan_path = $xml_doc->createElement('path', $value);
							$orphan_url = $xml_doc->createElement('url', 'http://'.$url.$value);
							$orphan_item->appendChild($orphan_path);
							$orphan_item->appendChild($orphan_url);
							$orphan_list->appendChild($orphan_item);
						}
						$orphan_elem->appendChild($orphan_list);
						// ftp data
						$ftp_elem = $xml_doc->createElement('ftp');
						$ftp_settings = $this->settings();
						$ftp_settings = $ftp_settings['ftp'];
						$ftp_settings_elem = $xml_doc->createElement('settings');
						foreach ($ftp_settings as $key => $value) {
							if(is_array($value)){
								$sett = $xml_doc->createElement($key);
								foreach ($value as $val) {
									if(is_null($val) || empty($val)){
										$temp = $xml_doc->createElement('value', 'null');
									} else {
										$temp = $xml_doc->createElement('value', $val);
									}
									$sett->appendChild($temp);
								}
							} else {
								$sett = $xml_doc->createElement($key, $value);
							}
							$ftp_settings_elem->appendChild($sett);
						}
						$ftp_elem->appendChild($ftp_settings_elem);
						$ftp_elem->appendChild($xml_doc->createElement('total', $ftp_temp['list_total']));
						$ftp_list = $xml_doc->createElement('list');
						foreach ($ftp_temp['list'] as $key => $value) {
							$ftp_item = $xml_doc->createElement('file');
							$ftp_path = $xml_doc->createElement('path', $value);
							$ftp_url = $xml_doc->createElement('url', 'http://'.$url.$value);
							$ftp_item->appendChild($ftp_path);
							$ftp_item->appendChild($ftp_url);
							$ftp_list->appendChild($ftp_item);
						}
						$ftp_elem->appendChild($ftp_list);
						// site data
						$site_elem = $xml_doc->createElement('site');
						$site_elem->appendChild($xml_doc->createElement('total', $site_temp['crawl']['links_total']));
						$site_list = $xml_doc->createElement('list');
						foreach ($site_temp['crawl']['links'] as $key => $value) {
							$site_item = $xml_doc->createElement('page');
							$site_path = $xml_doc->createElement('path', $value);
							$site_url = $xml_doc->createElement('url', 'http://'.$url.$value);
							$site_item->appendChild($site_path);
							$site_item->appendChild($site_url);
							$site_list->appendChild($site_item);
						}
						$site_elem->appendChild($site_list);
						// finish up
						$root->appendChild($orphan_elem);
						$root->appendChild($ftp_elem);
						$root->appendChild($site_elem);
						$xml_doc->appendChild($root);
						$xml_doc->normalizeDocument();
						// name, save and return
						$name = explode('.', $url);
						if(count($name) > 2){
							$name = $name[1];
						} else {
							$name = $name[0];
						}
						$name .= '_orphancrawl.xml';
						$xml_file = fopen($name, 'w');
						fwrite($xml_file, $xml_doc->saveXML());
						fclose($xml_file);
						$return = $name;
						break;
				}
				
				break;
		}
		// return results
		return $return;
	}
}

?>