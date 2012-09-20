#OrphanCrawler#

A collection of PHP classes to crawl a website and its FTP server, then comparing the two to find orphaned files.

##Classes##

###OrphanCrawler###

`orphan-crawler.class.php`

Can be used to operate one or both of the other classes in this project. Provides several ways of interacting with the other classes in order to make it more versatile and usable in many situations.

**Usage:**
```php
<?php
include 'orphan-crawler.class.php';

$crawler = new OrphanCrawler();
$crawler->ftp('www.example.com', 'fred', 'awesomepassword123');
$crawler->site('www.example.com');

$ftp_settings = array(
	'ftp'=>array(
		'passive'=>true,
		'file_types'=>array(
			'js',
			'css',
			'png'
		)
	)
);
$ftp_config = $crawler->settings($ftp_settings);

$site_settings = array(
	'site'=>array(
		'ignore_dirs'=>array(
			'cgi-bin',
			'_uploads'
		),
		'file_types'=>array(
			'aspx'
		)
	)
);
$site_config = $crawler->settings($site_settings);

$output = $crawler->output('compare', 'php');
echo "<pre>" . print_r($output, true) . "</pre>";

?>
```

**Output Formats**
- 'php' is the default output which provides a multi-dimensional array of the orphaned files, FTP output $list and Site output $links.

###SiteCrawler###

`site-crawler.class.php`

Given a starting URL, this class will find and navigate through all hyperlinks it can find, adding them to a list of crawled pages and a multi-dimensional array of the links contained on each page it crawls.

Optionally you can also pass arrays containing directories that should be ignored and additional filetypes to be accepted.

**Usage:**
```php
<?php
include 'site-crawler.class.php';

$crawler = new SiteCrawler('www.example.com');

$settings = array(
	'ignore_dirs'=>array(
		'cgi-bin',
		'_uploads'
	),
	'file_types'=>array(
		'aspx'
	)
);
$results = $crawler->settings($settings);

$output = $crawler->output('php');
echo "<pre>" . print_r($output, true) . "</pre>";

?>
```

**Output Formats**
- 'php' is the default output which provides a multi-dimensional array of crawled page count, crawled page list and a list of links-per-page.
- 'xml' output saves the list of links-per-page to an XML file. The file name is generated from the initial URL.
- 'sitemap' uses the sitemaps.org 0.9 schema to generate a Google-compatible Sitemap.xml file.

###FTPCrawler###

`ftp-crawler.class.php`

Given the host address, username and password of a site's FTP server, this class will navigate through all folders creating an array of all files found.

Optionally you can also pass an array of settings such as the directory to treat as root, directories to ignore and whether or not to use passive mode.

**Usage:**
```php
<?php
include 'ftp-crawler.class.php';

$crawler = new FTPCrawler('www.example.com', 'fred', 'awesomepassword123');

$settings = array(
	'passive'=>true,
	'file_types'=>array(
		'js',
		'css',
		'png'
	),
	'ignore_dirs'=>array(
		'cgi-bin',
		'_uploads'
	)
);
$results = $crawler->settings($settings);

$output = $crawler->output('php');
echo "<pre>" . print_r($output, true) . "</pre>";
?>
```

**Output Formats**
- 'php' is the default output which provides a multi-dimensional array of crawled page count, crawled page list and a list of links-per-page.

##To Do##

- Make relativePathFix() more adaptable and competent
  - Turn it into a full path handler rather than just fixing relative paths?
  - Process blog-style paths
- Allow for listing links to external websites/other protocols
  - Main problem with this is javascript links
- Add a list of files included on each page (images, flash files, etc) rather than just hyperlinks
- Look into other output formats
  - Add XML format to FTPCrawler
  - Add XML format to OrphanCrawler