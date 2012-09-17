SiteCrawler
===========

A simple PHP class to crawl through a site extracting hyperlinks.

Given a starting URL, this class will find and navigate through all hyperlinks it can find, adding them to a list of crawled pages and a multi-dimensional array of the links contained on each page it crawls.

Optionally you can also pass arrays containing directories that should be ignored and additional filetypes to be accepted.

Usage:
```php
<?php
include 'site-crawler.class.php';

// $crawler = new SiteCrawler($_start, $_ignore = null, $_types = array('html', 'htm', 'php'));
// $_start - URL to start crawling from
// $_ignore - an array of folders to be ignored
// $_types - an array of filetypes to allow
$crawler = new SiteCrawler('www.autohotkey.com'[, array('wiki', 'forum')[, array('html', 'htm', 'php', 'aspx')]]);

// $output = $crawler->output($type = 'xml');
// $type - the desired output format
$output = $crawler->output(['php'|'xml'|'sitemap'[, array('file_name'=>'my-list')]]);
?>
```

##Output Formats##
- 'php' is the default output which provides a multi-dimensional array of crawled page count, crawled page list and a list of links-per-page.
- 'xml' output saves the list of links-per-page to an xml file. The file name is generated from the initial URL unless a name is specified when calling the `output()` function.
- 'sitemap' uses the sitemaps.org 0.9 schema to generate a Google-compatible Sitemap.xml file.

##To Do##

- Make relativePathFix() more adaptable and competent
  - Turn it into a full path handler rather than just fixing relative paths?
  - Process blog-style paths
- Allow for listing links to external websites/other protocols
  - Main problem with this is javascript links
- Add a list of files included on each page (images, flash files, etc)
- Look into other output formats