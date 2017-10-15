<?php
require_once '/var/www/webcomic_crawler/app/include.php';
//require_once dirname(__FILE__) . '/include.php';

require_once APP_PATH . 'class_web_comic_rss.php';
$objWCR = new WebComicRss;
$objWCR -> startCrawl();
?>
