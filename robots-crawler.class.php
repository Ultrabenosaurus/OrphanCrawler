<?php

// Original PHP code by Chirp Internet: www.chirp.com.au
// Adapted to include 404 and Allow directive checking by Eric at LinkUp.com
// Adapted into a class to use when crawling an entire site by Dan at github.com/Ultrabenosaurus
	// only downloads robots.txt once per instance
	// keeps appropriate rules ("User-agent: *" and any agent your specify) in array
	// public function to compare a given path to all entries in array of rules
	// can change or view settings via public function
// Please acknowledge use of this code by including this header.

class RobotsCrawler{
	private $url;			// (string) contains the URL currently being crawled
	private $useragent;		// (string|boolean) stores custom user-agent or false
	private $rules;			// (array) all rules found that apply to current crawl
	
	// sets everything up ready to be used to check if paths can be crawled
	public function __construct($_url, $_useragent = false){
		// add "http://" us missing from URL to make parse_url() happy
		if(!preg_match('%^http://.*%', $_url)){
			$_url = 'http://'.$_url;
		}
		// populate all class variables
		$this->url = parse_url($_url);
		$this->useragent = $_useragent;
		$this->getRobots();
	}

	// check if a path is disallowed in robots.txt
	public function crawlAllowed($_path){
		// if there isn't a robots.txt, then we're allowed in
		if(empty($this->robotstxt)) return true;
		
		$isAllowed = true;
		// loop through all rules in array
		foreach($this->rules['disallow'] as $rule) {
			// check if page hits on a disallow rule
			if(preg_match("%".$rule."%", $_path)){
				$isAllowed = false;
				// if page is disallowed, see if there is an allow to override it
				foreach ($this->rules as $ruleA) {
					if(preg_match("%".$ruleA."%", $_path)){
						$isAllowed = true;
					}
				}
			// if not disallowed, assume allowed
			} else {
				$isAllowed = true;
			}
		}
		return $isAllowed;
	}
	
	// attempts to download robots.txt
	private function getRobots(){
		// location of robots.txt file, only pay attention to it if the server says it exists
		$path = "http://{$this->url['host']}/robots.txt";
		// attempt to use CURL to intercept 404 errors
		if(function_exists('curl_init')) {
			$handle = curl_init($path);
			curl_setopt($handle,  CURLOPT_RETURNTRANSFER, TRUE);
			$response = curl_exec($handle);
			$httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
			// check HTTP response code
			if($httpCode == 200) {
				$_robotstxt = explode("\n", $response);
			} else {
				$_robotstxt = false;
			}
			curl_close($handle);
		// if CURL is not installed, use file() with errors suppressed
		} else {
			$_robotstxt = @file($path);
		}
		// if a robots.txt file was found, parse it for rules
		if($_robotstxt){
			$this->parseRules($_robotstxt);
		}
	}
	
	// parses robots.txt file for rules
	private function parseRules($robotstxt){
		// make list of useragents to look for
		$agents = array(preg_quote('*'));
		if($this->useragent) $agents[] = preg_quote($this->useragent, '/');
		$agents = implode('|', $agents);

		// look for rules that apply to the agents
		$_rules = array();
		$ruleApplies = false;
		foreach($robotstxt as $line) {
			// skip blank lines
			if(!$line = trim($line)) continue;

			// following rules only apply if User-agent matches $useragent or '*'
			if(preg_match('/^\s*User-agent: (.*)/i', $line, $match)) {
				$ruleApplies = preg_match("/($agents)/i", $match[1]);
				continue;
			}
			// if rules are found, clean them up and make them regex-safe
			if($ruleApplies) {
				list($type, $rule) = explode(':', $line, 2);
				$type = trim(strtolower($type));
				// add rules that apply to array for testing
				$temp = str_ireplace('*', '.*', str_ireplace('.', '\.', str_ireplace('?', '\?', str_ireplace('$', '\$', trim($rule)))));
				if(strrpos($temp, '/') == (strlen($temp)-1) || strrpos($temp, '=') == (strlen($temp)-1) || strrpos($temp, '?') == (strlen($temp)-1)){
					$temp .= '.*';
				}
				$_rules[$type][] = $temp;
			}
		}
		// add found rules to class variable
		$this->rules = $_rules;
	}
	
	// allows you to change the settings so that the same instance can be reused
	public function settings($options = 'get'){
		// check if $options is a populated array
		if(is_array($options) && count($options) > 0){
			// loop through array
			foreach ($options as $setting => $value) {
				// switch on the array keys to determine what to do
				switch ($setting) {
					// check if provided value is a string
					case 'url':
						if(gettype($value) === 'string'){
							// check if value can be parsed as a URL
							if(parse_url($value)){
								$this->url = parse_url($value);
								$return[$setting] = true;
							// if it can't normally, try leading with "http://"
							} elseif(parse_url("http://".$value)) {
								$this->url = parse_url("http://".$value);
								$return[$setting] = true;
							// otherwise return an error
							} else {
								$return[$setting] = array('value'=>$value, 'error'=>"Given value could not be parsed as a URL.");
							}
						// if not a sting return an error
						} else {
							$return[$setting] = array('value'=>$value, 'error'=>"Given value was of type ".gettype($value).", <em>string</em> required.");
						}
						break;
					// check if provided value is a string
					case 'useragent':
						if(gettype($value) === 'string'){
							$this->useragent = $value;
							$return[$setting] = true;
						// if it isn't return an error
						} else {
							$return[$setting] = array('value'=>$value, 'error'=>"Given value was of type ".gettype($value).", <em>string</em> required.");
						}
						break;
					// return an error by default
					default:
						$return[$setting] = array('value'=>$value, 'error'=>"This setting does not exist or cannot currently be changed manually.");
						break;
				}
			}
		// if $options is not an array, switch to see what to do
		} else {
			switch ($options) {
				// return all properties to the user
				case 'get':
					$reflect = new ReflectionClass($this);
					$properties = $reflect->getProperties();
					// loop through properties, add to array
					foreach ($properties as $prop) {
						$name = $prop->getName();
						$return[$name] = $this->{$name};
					}
					break;
			}
		}
		return $return;
	}
}

?>