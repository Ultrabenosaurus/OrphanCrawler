<?php

include 'site-crawler.class.php';

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset='utf-8' />
	<title>Crawler Class Test</title>
	<style>
	body {
		font-family: Calibri;
	}
	</style>
</head>
	<body>
		<?php
		
		$url = 'www.autohotkey.com';
		$type = 'xml';
		$options = array('use_date'=>true);
		
		$crawler = new SiteCrawler($url, array('wiki', 'forum'));
		$output = $crawler->output($type, $options);
		
		switch($type){
			case 'xml':
				echo "<a href='".$output."' target='_blank'>" . $output . "</a>";
				break;
			case 'php':
				echo "<pre>" . print_r($output, true) . "</pre>";
				break;
		}
		
		?>
	</body>
</html>