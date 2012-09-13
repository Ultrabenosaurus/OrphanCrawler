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
		
		$type = 'xml';
		$options = array('file_name'=>'autohotkey_'.date('Y-m-d'));
		
		$crawler = new SiteCrawler('www.autohotkey.com', array('wiki', 'forum'));
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