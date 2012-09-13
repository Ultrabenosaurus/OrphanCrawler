<?php

class SiteCrawler{
	private $url;
	private $pages;
	private $current_path;
	private $links;
	private $visited;
	private $ignore_dirs;
	private $file_types;
	private $doc;
	
	// adds initial values to variables, starts the crawl
	public function __construct($start, $ignore = null, $types = array('html', 'htm', 'php')){
		// user-changeable settings
		$this->url = $start;
		$this->ignore_dirs = $ignore;
		$this->file_types = $types;
		
		// variables used for crawling
		$this->pages = array('/');
		$this->current_path = '/';
		$this->links = array();
		$this->visited = array();
		
		// empty DOMDocument for loading pages and finding links
		$this->doc = new DOMDocument();
		libxml_use_internal_errors(true);
		$this->doc->strictErrorChecking = false;
		$this->doc->recover = true;
		
		// start crawling the site
		$this->crawl();
	}
	
	// opens first entry in $pages, extracts all hyperlinks, adds them to $links
	private function crawl(){
		// set the file path, extract directory structure, open file
		$path = 'http://'.$this->url.$this->pages[0];
		$this->setCurrentPath();
		$this->doc->loadHTMLFile($path);
		
		// find all <a> tags in the page
		$a_tags = $this->doc->getElementsByTagName('a');
		// if links were found, loop through all of them
		if(!is_null($a_tags)){
			foreach ($a_tags as $link) {
				// find the href attribute of each link
				foreach ($link->attributes as $attrib) {
					if(strtolower($attrib->name) == 'href'){
						// make sure its not external, javascript or to an anchor
						if(count(explode(':', $attrib->value)) < 2 && count(explode('#', $attrib->value)) < 2){
							// check if any directories should be ignored
							if(!is_null($this->ignore_dirs)){
								$add = true;
								// loop through ignored directories, compare to the path in the link
								foreach ($this->ignore_dirs as $dir) {
									if(array_search($dir, explode('/', $attrib->value)) === false){
										$foo = 'bar';
									} else {
										$add = false;
									}
								}
								// if link doesn't go to a directory that should be ignored, add to array
								if($add){
									$this->links[$this->pages[0]][] = $this->relativePathFix($attrib->value);
								}
							// if no directories should be ignored, add everything
							} else {
								$this->links[$this->pages[0]][] = $this->relativePathFix($attrib->value);
							}
						}
					}
				}
			}
		}
		// merge and sort all arrays
		$this->arrayShuffle();
	}
	
	// changes $current_path used for fixing relative paths
	private function setCurrentPath(){
		$dirs = explode('/', $this->pages[0]);
		// if last character is a / then just use it
		if(empty($dirs[count($dirs)-1]) || is_null($dirs[count($dirs)-1])){
			$this->current_path = $this->pages[0];
		// if end of path was a filename, remove it and add a /
		} else {
			array_pop($dirs);
			$this->current_path = implode('/', $dirs).'/';
		}
	}
	
	// fixes relative paths (to/file.php) into absolute paths (/path/to/file.php)
	private function relativePathFix($path){
		$dirs = explode('/', $path);
		// check if link goes up a directory
		if($dirs[0] == '..'){
			$new_path = explode('/', $this->current_path);
			if(count($dirs) == 2){
				array_pop($new_path);
				array_pop($new_path);
				$new_path = implode('/', $new_path).'/';
			} else {
				// remove slash from end of current path to ensure single slashes
				if(empty($new_path[count($new_path)-1]) || is_null($new_path[count($new_path)-1])){
					array_pop($new_path);
				}
				// go up directories
				while($dirs[0] == '..'){
					array_shift($dirs);
					array_pop($new_path);
				}
				// stick the two paths together to get new path
				$new_path = implode('/', $new_path).'/';
				$new_path .= implode('/', $dirs);
			}
		// if link it to same dir, remove the ./
		} elseif($dirs[0] == '.'){
			$new_path = $this->current_path.substr($path, 2);
		// if the link starts at root, use it without modification
		} elseif(empty($dirs[0]) || is_null($dirs[0])){
			$new_path = $path;
		} elseif(strlen($dirs[0]) == 2){
			$new_path = '/'.$path;
		// default to adding the link's value to the end of the current directory
		} else {
			$new_path = $this->current_path.$path;
		}
		
		// if the link doesn't point to a file or query string, but has no end slash, add one
		if(count(explode('.', $new_path)) < 2 && count(explode('?', $new_path)) < 2 && substr($new_path, -1) != '/'){
			$new_path .= '/';
		}
		return $new_path;
	}
	
	// sorts entries in $links, moves entries to $pages, validates entries in $pages
	private function arrayShuffle(){
		// check if arrays exist before trying to use them
		if(isset($this->links[$this->pages[0]]) && is_array($this->links[$this->pages[0]])){
			// remove duplicate values
			$this->links[$this->pages[0]] = array_unique($this->links[$this->pages[0]]);
			// find all links that are not queued or visited and add to the queue
			$this->pages = array_merge($this->pages, array_diff($this->links[$this->pages[0]], $this->pages, $this->visited));
			// sort links
			sort($this->links[$this->pages[0]]);
		}
		// find all links in the queue that point to files
		foreach ($this->pages as $path) {
			if(count(explode('.', $path)) > 1){
				// get the file extension
				$file = explode('.', $path);
				$type = count($file);
				$type = $file[$type-1];
				// remove any links that are to files NOT on the accept list (deafult: html, htm, php)
				if(array_search($type, $this->file_types) === false){
					while($key = array_search($path, $this->pages)){
						array_splice($this->pages, $key, 1);
					}
				}
			}
		}
		
		// add current link to $visited, remove from $pages, sort $pages
		$this->visited[] = $this->pages[0];
		array_shift($this->pages);
		sort($this->pages);
		
		// if the queue is not empty, crawl again
		if(count($this->pages) > 0){
			$this->crawl();
		}
	}
	
	// formats array contents ready for display (default: php)
	public function output($type = 'php', $options = null){
		/* 
		  $type
		  	a string containing the name of the desired output format.
		  	format descriptions:
		  	- php:		 returns a multi-dimensional array containing the number of pages
		  				 crawled; a list of all pages crawled ($visited); and a list of
		  				 all the links found on each page ($links)
		  	- xml:		 turns the $links array into an XML document following the same
		  				 format as the array (page_crawled = array(links_found))
		  	- sitemap:	 creates a Google-compatible Sitemap.xml file according to the
		  				 sitemaps.org 0.9 schema. 
		*/
		/*
		  $options
		  	an array of data that can be used to change the default behavior of any
		  	supported format.
		  	do not use this to add additional formats - that is the job of the switch()
		  	
		  	- file_name: string > the name to use for XML output, without file extension
		  	- use_date:	 boolean > append date to the file name in the format '_YYYY-MM-DD'
		  				 string > custom date format to be used
		*/
		switch($type){
			case 'php':
			// for php format, return an array of page count, $visited and $links
				$return['pages_crawled']['total'] = count($this->visited);
				sort($this->visited);
				$return['pages_crawled']['paths'] = $this->visited;
				ksort($this->links);
				$return['page_links'] = $this->links;
				break;
			case 'xml':
			// for xml, make an xml document of $links and save to the server
			// using the domain that was crawled as the filename
				$xml_doc = new DOMDocument();
				$xml_doc->formatOutput = true;
				$paths = $xml_doc->createElement('pages');
				// loop through all the arrays in $links
				foreach ($this->links as $file_path => $hrefs) {
					// create element for each array
					$path = $xml_doc->createElement('page');
					$path_id = $xml_doc->createAttribute('id');
					// use array's key (path crawled) as element's ID
					$path_id->value = $file_path;
					$path->appendChild($path_id);
					// loop through all links in each array, adding to element
					foreach ($hrefs as $href) {
						$link = $xml_doc->createElement('link', $href);
						$path->appendChild($link);
					}
					$paths->appendChild($path);
				}
				$xml_doc->appendChild($paths);
				$xml_doc->normalizeDocument();
				
				// if a name is provided, use that
				if(isset($options) && isset($options['file_name'])){
					$name = $options['file_name'];
				} else {
				// otherwise make the name from the $url that was crawled
					$name = explode('.', $this->url);
					if(count($name) > 2){
						$name = $name[1];
					} else {
						$name = $name[0];
					}
				}
				if(isset($options) && isset($options['use_date'])){
					$name .= '_';
					if($options['use_date'] === true){
						$name .= date('Y-m-d');
					} elseif(gettype($options['use_date']) === 'string'){
						$name .= date($options['use_date']);
					}
				}
				// write data to file and return filename
				$xml_file = fopen($name.'.xml', 'w');
				fwrite($xml_file, $xml_doc->saveXML());
				fclose($xml_file);
				$return = $name.'.xml';
				break;
			case 'sitemap':
			// for sitemap format, make an xml document of $visited and save to the server
			// Google-compatible sitemaps.org schema used
				sort($this->visited);
				$xml_doc = new DOMDocument();
				$xml_doc->formatOutput = true;
				
				// use Google-compatible Sitemap format/schema
				$paths = $xml_doc->createElement('urlset');
				$xmlns = $xml_doc->createAttribute('xmlns');
				$xmlns->value = "http://www.sitemaps.org/schemas/sitemap/0.9";
				$paths->appendChild($xmlns);
				// loop through all entries in $visited
				foreach ($this->visited as $file_path) {
					// create necessary elements
					$url = $xml_doc->createElement('url');
					$loc = $xml_doc->createElement('loc');
					$path = $xml_doc->createTextNode('http://'.$this->url.urlencode($file_path));

					// assemble elements in order
					$loc->appendChild($path);
					$url->appendChild($loc);
					$paths->appendChild($url);
				}
				$xml_doc->appendChild($paths);
				$xml_doc->normalizeDocument();
				
				// save file, return filename
				$xml_file = fopen('Sitemap.xml', 'w');
				fwrite($xml_file, $xml_doc->saveXML());
				fclose($xml_file);
				$return = 'Sitemap.xml';
				break;
		}
		return $return;
	}
}

?>