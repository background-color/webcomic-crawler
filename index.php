<?php
require_once dirname(__FILE__) . '/library/include.php';



require_once HOME_PATH . 'library/class_web_comic_rss.php';
$objWCR = new WebComicRss;
$objWCR -> startCrawl();


/*

foreach($urlList as $comicId => $comicUrl){
	if(!$comicUrl)	break;
	
	if(!$getHtmlDom	= file_get_html($comicUrl))	break;
	print "<br>test!----<br>";
	
	foreach($getHtmlDom -> find($listDom[$comicId]) as $listArea){
			foreach($listArea -> find($titleDom[$comicId]) as $title){
				echo $title -> plaintext . "<br>";
			}
			foreach($listArea -> find($urlDom[$comicId]) as $url){
				echo $url -> href . "<br>";
			}
	}
		
	//メモリクリア
	$getHtmlDom->clear();
}

*/

?>
