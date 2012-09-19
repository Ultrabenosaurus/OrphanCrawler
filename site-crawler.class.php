<?php

class SiteCrawler{
	private $url; 			// (string) initial to start crawling from
	private $pages;			// (array) queue of pages yet to be crawled
	private $current_path;	// (string) directory of current page
	private $links;			// (array) multi-dimensional array of links-per-page
	private $visited;		// (array) list of all pages visited during crawl
	private $ignore_dirs;	// (array|null) any directories to be ignored [currently applies to any depth]
	private $file_types;	// (array) extensions of files to be crawled
	private $doc;			// (DOMDocument) object used to open pages
	
	// adds initial values to variables, starts the crawl
	public function __construct($_start){
		// starting url
		$this->url = $_start;
		
		// variables used for crawling
		$this->pages = array('/');
		$this->current_path = '/';
		$this->links = array();
		$this->visited = array();
		$this->ignore_dirs = null;
		$this->file_types = array('html', 'htm', 'php');
		
		// empty DOMDocument for loading pages and finding links
		$this->doc = new DOMDocument();
		libxml_use_internal_errors(true);
		$this->doc->strictErrorChecking = false;
		$this->doc->recover = true;
	}
	
	// allows user to change variables if values are valid
	public function settings($options = 'get'){
		// ensure settings are given
		if(is_array($options) && count($options) > 0){
			// loop through all settings given
			foreach ($options as $setting => $value) {
				// check for existence of desired variable
				if(property_exists('SiteCrawler', $setting)){
					// switch through all variables for validation
					switch ($setting) {
						// check if desired value is a string
						case 'url':
							if(gettype($value) === 'string'){
								// set variable, return true
								$this->{$value} = $value;
								$return[$setting] = true;
							} else {
								// return errors 
								$return[$setting] = array('value'=>$value, 'error'=>'Given value was type '.gettype($value).', <em>string</em> required.');
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
										$return[$setting][$type] = array('value'=>$type, 'error'=>'Given value was type '.gettype($type).', <em>string</em> required.');
									}
								}
								// remove duplicates from array
								$this->{$setting} = array_unique($this->$setting);
							} else {
								// return errors if not array
								$return[$setting] = array('value'=>$value, 'error'=>'Given value was type '.gettype($value).', <em>array</em> required.');
							}
							break;
						// check if value is populated array
						case 'ignore_dirs':
							if (gettype($value) === 'array' && count($value) > 0) {
								// loop through array
								foreach ($value as $type) {
									// check if value is a string
									if(gettype($type) === 'string'){
										// set variable, return true
										$this->{$setting}[] = $type;
										$return[$setting][$type] = true;
									} else {
										// return errors if not string
										$return[$setting][$type] = array('value'=>$type, 'error'=>'Given value was type '.gettype($type).', <em>string</em> required.');
									}
								}
								// remove duplicates from array
								$this->{$setting} = array_unique($this->$setting);
							// check if value is null
							} elseif(is_null($value)) {
								// set variable, return true
								$this->{$setting} = null;
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
				// if $options is 'get', print all variables but $pages (usually empty), $visited and $links (could be massive), and $doc (DOMDocument object)
				case 'get':
					// initiate a ReflectionClass object, gather properties
					$reflect = new ReflectionClass($this);
					$properties = $reflect->getProperties();
					// loop through properties, add to array
					foreach ($properties as $prop) {
						$name = $prop->getName();
						if($name !== 'pages' && $name !== 'links' && $name !== 'visited' && $name !== 'doc'){
							$return[$name] = $this->{$name};
						}
					}
					break;
			}
		}
		return $return;
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
							$this->links[$this->pages[0]][] = $this->relativePathFix($attrib->value);
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
				$qry = false;
				foreach ($file as $key => $value){
					if (count(explode('?', $value)) > 1) {
						$file[$key] = explode('?', $value);
						$qry = $key;
					}
				}
				if($qry){
					$type = $file[$qry][0];
				} else {
					$type = count($file);
					$type = $file[$type-1];
				}
				// remove any links that are to files NOT on the accept list (deafult: html, htm, php)
				if(array_search($type, $this->file_types) === false){
					while($key = array_search($path, $this->pages)){
						array_splice($this->pages, $key, 1);
					}
				}
			}
			if(!is_null($this->ignore_dirs)){
				// loop through ignored directories, compare to the path in the link
				foreach ($this->ignore_dirs as $dir) {
					if(array_search($dir, explode('/', $path)) !== false){
						while($key = array_search($path, $this->pages)){
							array_splice($this->pages, $key, 1);
						}
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
	
	// formats array contents ready for display (default: xml)
	public function output($type = 'xml'){
		// crawl the site
		$this->crawl();
		
		// switch statement to make it easy to add different output formats
		switch($type){
			case 'php':
			// for php format, return an array of page count, $visited and $links
				$return['crawl']['total'] = count($this->visited);
				sort($this->visited);
				$return['crawl']['paths'] = $this->visited;
				$temp = array();
				foreach ($this->links as $key => $value) {
					foreach ($value as $key => $value) {
						$temp[] = $value;
					}
				}
				$temp = array_unique($temp);
				sort($temp);
				$return['crawl']['links:'.count($temp)] = $temp;
				ksort($this->links);
				// loop through all arrays in $links to add the per-page total
				foreach ($this->links as $path => $links) {
					$return['link_map'][$path.':'.count($links)] = $links;
				}
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
				// create filename from domain name
				$name = explode('.', $this->url);
				if(count($name) > 2){
					$name = $name[1];
				} else {
					$name = $name[0];
				}
				$xml_file = fopen($name.'.xml', 'w');
				fwrite($xml_file, $xml_doc->saveXML());
				fclose($xml_file);
				$return = $name.'.xml';
				break;
		}
		return $return;
	}
}

?>