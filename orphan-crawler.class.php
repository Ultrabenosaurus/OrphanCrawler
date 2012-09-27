<?php

// Original PHP code by Dan at github.com/Ultrabenosaurus
// Please acknowledge use of this code by including this header.

include_once 'site-crawler.class.php';
include_once 'ftp-crawler.class.php';

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
						$this->site($value['url'], $value['robots']);
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
	public function site($_start, $_robots = array(false, false)){
		$this->_site = new SiteCrawler($_start, $_robots);
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
				$name = explode('.', $url);
				if(count($name) > 2){
					$name = $name[1];
				} else {
					$name = $name[0];
				}
				
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
						$name .= '_orphancrawl.xml';
						$xml_file = fopen($name, 'w');
						fwrite($xml_file, $xml_doc->saveXML());
						fclose($xml_file);
						$return = $name;
						break;
					case 'html':
						// make directory for output
						$dir = './OrphanCrawler_Report/'.$name.'/'.date('Y-m-d_H-i-s').'/';
						if(!mkdir($dir, 0, true)){
							$return = 'could not create directory';
							break;
						}
						// create CSS file
						$css_file = fopen($dir.'style.css', 'w');
						fwrite($css_file, "body{font-family: Calibri;} #container{position: relative; width: 700px; margin: 0 auto;} ul{list-style-type: none;}");
						fclose($css_file);
						// orphans page
						// new DOMDocument
						$orphan_doc = new DOMDocument();
						$orphan_doc->formatOutput = true;
						// setup the document structure
						$orphan_html = $orphan_doc->createElement('html');
						$orphan_head = $orphan_doc->createElement('head');
						$orphan_style = $orphan_doc->createElement('link');
						$orphan_style_rel = $orphan_doc->createAttribute('rel');
						$orphan_style_rel->value = 'stylesheet';
						$orphan_style_href = $orphan_doc->createAttribute('href');
						$orphan_style_href->value = './style.css';
						$orphan_style->appendChild($orphan_style_rel);
						$orphan_style->appendChild($orphan_style_href);
						$orphan_head->appendChild($orphan_style);
						$orphan_title = $orphan_doc->createElement('title');
						$orphan_title->appendChild($orphan_doc->createTextNode($url.' | OrphanCrawler'));
						$orphan_head->appendChild($orphan_title);
						$orphan_html->appendChild($orphan_head);
						$orphan_body = $orphan_doc->createElement('body');
						// container
						$orphan_container = $orphan_doc->createElement('div');
						$orphan_container_id = $orphan_doc->createAttribute('id');
						$orphan_container_id->value = 'container';
						$orphan_container->appendChild($orphan_container_id);
						$orphan_container->appendChild($orphan_doc->createElement('br'));
						$orphan_container->appendChild($orphan_doc->createElement('h2', 'OrphanCrawler Results'));
						$orphan_container->appendChild($orphan_doc->createElement('br'));
						$orphan_back = $orphan_doc->createElement('a', "&laquo; Back");
						$orphan_back_href = $orphan_doc->createAttribute('href');
						$orphan_back_href->value = "./";
						$orphan_back->appendChild($orphan_back_href);
						$orphan_container->appendChild($orphan_back);
						$orphan_container->appendChild($orphan_doc->createElement('br'));
						$orphan_container->appendChild($orphan_doc->createElement('br'));
						// content
						$orphan_content = $orphan_doc->createElement('div');
						$orphan_content_id = $orphan_doc->createAttribute('id');
						$orphan_content_id->value = 'content';
						$orphan_content->appendChild($orphan_content_id);
						$orphan_content->appendChild($orphan_doc->createElement('p', 'Total: '.count($comp_temp)));
						$orphan_results = $orphan_doc->createElement('ol');
						$orphan_results->appendChild($orphan_doc->createElement('strong', 'Orphan Files List:'));
						foreach ($comp_temp as $key => $value) {
							$orphan_results_item = $orphan_doc->createElement('li');
							$orphan_results_item_link = $orphan_doc->createElement('a', $value);
							$orphan_results_item_link_href = $orphan_doc->createAttribute('href');
							$orphan_results_item_link_href->value = 'http://'.$url.$value;
							$orphan_results_item_link->appendChild($orphan_results_item_link_href);
							$orphan_results_item->appendChild($orphan_results_item_link);
							$orphan_results->appendChild($orphan_results_item);
						}
						$orphan_content->appendChild($orphan_results);
						// finish up and save the file
						$orphan_container->appendChild($orphan_content);
						$orphan_body->appendChild($orphan_container);
						$orphan_html->appendChild($orphan_body);
						$orphan_doc->appendChild($orphan_html);
						$orphan_doc->normalizeDocument();
						$orphan_doc->saveHTMLFile($dir.'orphans.html');
						
						// site page
						// new DOMDocument
						$site_doc = new DOMDocument();
						$site_doc->formatOutput = true;
						// setup the document head
						$site_html = $site_doc->createElement('html');
						$site_head = $site_doc->createElement('head');
						$site_style = $site_doc->createElement('link');
						$site_style_rel = $site_doc->createAttribute('rel');
						$site_style_rel->value = 'stylesheet';
						$site_style_href = $site_doc->createAttribute('href');
						$site_style_href->value = './style.css';
						$site_style->appendChild($site_style_rel);
						$site_style->appendChild($site_style_href);
						$site_head->appendChild($site_style);
						$site_title = $site_doc->createElement('title');
						$site_title->appendChild($site_doc->createTextNode($url.' | SiteCrawler'));
						$site_head->appendChild($site_title);
						$site_html->appendChild($site_head);
						$site_body = $site_doc->createElement('body');
						// container
						$site_container = $site_doc->createElement('div');
						$site_container_id = $site_doc->createAttribute('id');
						$site_container_id->value = 'container';
						$site_container->appendChild($site_container_id);
						$site_container->appendChild($site_doc->createElement('br'));
						$site_container->appendChild($site_doc->createElement('h2', 'SiteCrawler Results'));
						$site_container->appendChild($site_doc->createElement('br'));
						$site_back = $site_doc->createElement('a', "&laquo; Back");
						$site_back_href = $site_doc->createAttribute('href');
						$site_back_href->value = "./";
						$site_back->appendChild($site_back_href);
						$site_container->appendChild($site_back);
						$site_container->appendChild($site_doc->createElement('br'));
						$site_container->appendChild($site_doc->createElement('br'));
						// content
						$site_content = $site_doc->createElement('div');
						$site_content_id = $site_doc->createAttribute('id');
						$site_content_id->value = 'content';
						$site_content->appendChild($site_content_id);
						$site_content->appendChild($site_doc->createElement('p', 'Total: '.$site_temp['crawl']['links_total']));
						$site_results = $site_doc->createElement('ol');
						$site_results->appendChild($site_doc->createElement('strong', 'Site Links List:'));
						foreach ($site_temp['crawl']['links'] as $key => $value) {
							$site_results_item = $site_doc->createElement('li');
							$site_results_item_link = $site_doc->createElement('a', $value);
							$site_results_item_link_href = $site_doc->createAttribute('href');
							$site_results_item_link_href->value = 'http://'.$url.$value;
							$site_results_item_link->appendChild($site_results_item_link_href);
							$site_results_item->appendChild($site_results_item_link);
							$site_results->appendChild($site_results_item);
						}
						$site_content->appendChild($site_results);
						
						// finish up and save the file
						$site_container->appendChild($site_content);
						$site_body->appendChild($site_container);
						$site_html->appendChild($site_body);
						$site_doc->appendChild($site_html);
						$site_doc->normalizeDocument();
						$site_doc->saveHTMLFile($dir.'site.html');
						
						// ftp page
						// new DOMDocument
						$ftp_doc = new DOMDocument();
						$ftp_doc->formatOutput = true;
						// setup the document head
						$ftp_html = $ftp_doc->createElement('html');
						$ftp_head = $ftp_doc->createElement('head');
						$ftp_style = $ftp_doc->createElement('link');
						$ftp_style_rel = $ftp_doc->createAttribute('rel');
						$ftp_style_rel->value = 'stylesheet';
						$ftp_style_href = $ftp_doc->createAttribute('href');
						$ftp_style_href->value = './style.css';
						$ftp_style->appendChild($ftp_style_rel);
						$ftp_style->appendChild($ftp_style_href);
						$ftp_head->appendChild($ftp_style);
						$ftp_title = $ftp_doc->createElement('title');
						$ftp_title->appendChild($ftp_doc->createTextNode($url.' | FTPCrawler'));
						$ftp_head->appendChild($ftp_title);
						$ftp_html->appendChild($ftp_head);
						$ftp_body = $ftp_doc->createElement('body');
						// container
						$ftp_container = $ftp_doc->createElement('div');
						$ftp_container_id = $ftp_doc->createAttribute('id');
						$ftp_container_id->value = 'container';
						$ftp_container->appendChild($ftp_container_id);
						$ftp_container->appendChild($ftp_doc->createElement('br'));
						$ftp_container->appendChild($ftp_doc->createElement('h2', 'FTPCrawler Results'));
						$ftp_container->appendChild($ftp_doc->createElement('br'));
						$ftp_back = $ftp_doc->createElement('a', "&laquo; Back");
						$ftp_back_href = $ftp_doc->createAttribute('href');
						$ftp_back_href->value = "./";
						$ftp_back->appendChild($ftp_back_href);
						$ftp_container->appendChild($ftp_back);
						$ftp_container->appendChild($ftp_doc->createElement('br'));
						$ftp_container->appendChild($ftp_doc->createElement('br'));
						// content
						$ftp_content = $ftp_doc->createElement('div');
						$ftp_content_id = $ftp_doc->createAttribute('id');
						$ftp_content_id->value = 'content';
						$ftp_content->appendChild($ftp_content_id);
						// FTP settings
						$ftp_settings = $ftp_doc->createElement('ul');
						$ftp_settings->appendChild($ftp_doc->createElement('strong', 'FTP settings:'));
						foreach ($ftp_temp['details'] as $key => $value) {
							$ftp_settings->appendChild($ftp_doc->createElement('li', "[$key] => $value"));
						}
						$ftp_content->appendChild($ftp_settings);
						$ftp_container->appendChild($ftp_doc->createElement('br'));
						// FTP results
						$ftp_content->appendChild($ftp_doc->createElement('p', 'Total: '.$ftp_temp['list_total']));
						$ftp_results = $ftp_doc->createElement('ol');
						$ftp_results->appendChild($ftp_doc->createElement('strong', 'FTP Files List:'));
						foreach ($ftp_temp['list'] as $key => $value) {
							$ftp_results_item = $ftp_doc->createElement('li');
							$ftp_results_item_link = $ftp_doc->createElement('a', $value);
							$ftp_results_item_link_href = $ftp_doc->createAttribute('href');
							$ftp_results_item_link_href->value = 'http://'.$url.$value;
							$ftp_results_item_link->appendChild($ftp_results_item_link_href);
							$ftp_results_item->appendChild($ftp_results_item_link);
							$ftp_results->appendChild($ftp_results_item);
						}
						$ftp_content->appendChild($ftp_results);
						
						// finish up and save the file
						$ftp_container->appendChild($ftp_content);
						$ftp_body->appendChild($ftp_container);
						$ftp_html->appendChild($ftp_body);
						$ftp_doc->appendChild($ftp_html);
						$ftp_doc->normalizeDocument();
						$ftp_doc->saveHTMLFile($dir.'ftp.html');
						
						// index page
						// new DOMDocument
						$index_doc = new DOMDocument();
						$index_doc->formatOutput = true;
						// setup the document head
						$index_html = $index_doc->createElement('html');
						$index_head = $index_doc->createElement('head');
						$index_style = $index_doc->createElement('link');
						$index_style_rel = $index_doc->createAttribute('rel');
						$index_style_rel->value = 'stylesheet';
						$index_style_href = $index_doc->createAttribute('href');
						$index_style_href->value = './style.css';
						$index_style->appendChild($index_style_rel);
						$index_style->appendChild($index_style_href);
						$index_head->appendChild($index_style);
						$index_title = $index_doc->createElement('title');
						$index_title->appendChild($index_doc->createTextNode($url.' | Index'));
						$index_head->appendChild($index_title);
						$index_html->appendChild($index_head);
						$index_body = $index_doc->createElement('body');
						// container
						$index_container = $index_doc->createElement('div');
						$index_container_id = $index_doc->createAttribute('id');
						$index_container_id->value = 'container';
						$index_container->appendChild($index_container_id);
						$index_container->appendChild($index_doc->createElement('br'));
						$index_container->appendChild($index_doc->createElement('h2', 'OrphanCrawler Index Page'));
						$index_container->appendChild($index_doc->createElement('br'));
						// content
						$index_content = $index_doc->createElement('div');
						$index_content_id = $index_doc->createAttribute('id');
						$index_content_id->value = 'content';
						$index_content->appendChild($index_content_id);
						$index_orphan_link = $index_doc->createElement('a', 'OrphanCrawler Results - '.count($comp_temp).' files');
						$index_orphan_link_href = $index_doc->createAttribute('href');
						$index_orphan_link_href->value = "./orphans.html";
						$index_orphan_link->appendChild($index_orphan_link_href);
						$index_content->appendChild($index_orphan_link);
						$index_content->appendChild($index_doc->createElement('br'));
						$index_site_link = $index_doc->createElement('a', 'SiteCrawler Results - '.$site_temp['crawl']['links_total'].' files');
						$index_site_link_href = $index_doc->createAttribute('href');
						$index_site_link_href->value = "./site.html";
						$index_site_link->appendChild($index_site_link_href);
						$index_content->appendChild($index_site_link);
						$index_content->appendChild($index_doc->createElement('br'));
						$index_ftp_link = $index_doc->createElement('a', 'FTPCrawler Results - '.$ftp_temp['list_total'].' files');
						$index_ftp_link_href = $index_doc->createAttribute('href');
						$index_ftp_link_href->value = "./ftp.html";
						$index_ftp_link->appendChild($index_ftp_link_href);
						$index_content->appendChild($index_ftp_link);
						$index_content->appendChild($index_doc->createElement('br'));
						// finish up and save the file
						$index_container->appendChild($index_content);
						$index_body->appendChild($index_container);
						$index_html->appendChild($index_body);
						$index_doc->appendChild($index_html);
						$index_doc->normalizeDocument();
						$index_doc->saveHTMLFile($dir.'index.html');
						$return = $dir;
						break;
				}
				
				break;
		}
		// return results
		return $return;
	}
}

?>