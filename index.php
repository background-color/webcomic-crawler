<?php
require_once dirname(__FILE__) . '/library/include.php';


require_once HOME_PATH . 'library/class_web_comic_rss.php';
$objWCR = new WebComicRss;
$objWCR -> startCrawl();



/*
require_once HOME_PATH . 'library/Goutte/goutte.phar';
use Goutte\Client;
			$ret	= array(
				"dom_upd_list" => "div#totalArea div.totalinfo"
				,"dom_upd_title" => "div.eachStoryText h4"
				,"dom_upd_url" => "div.eachStoryBtn a"
				,"last_title"	=> ""
				,"last_url"	=> ""
			);
			
			
			$client = new Client();
			if(!$crawler = $client->request('GET',"http://192.168.0.20/001.html")){
			}

			$crawler -> filter($ret["dom_upd_list"]) -> each(function($element, $i) use (&$ret, &$getComicUpdList){
				//global $ret;
				print_r($ret);
				print "<br>" . $element -> filter($ret["dom_upd_title"]) -> text();
				
				
				$getTitle	= trim($element -> filter($ret["dom_upd_title"]) -> text());
				$getUrl		= trim($element -> filter($ret["dom_upd_url"]) -> attr('href'));
				
				print "<br>" . $getTitle;
				
				
				//最終更新と比較 同じであればチェック終了
				if($ret["last_title"] == $getTitle ||  $ret["last_url"] == $getUrl){
					return false;
				}
				$getComicUpdList[]	= array($getTitle, $getUrl);
			});
			
			
*/

exit;

/*
$urlList = array(
	0 => "http://comic-meteor.jp/michiwari/"
	,1 => "http://comic-polaris.jp/odette/"
	,2 => "http://www.comico.jp/articleList.nhn?titleNo=2"

);
*/
$urlList = array(
	0 => "http://192.168.0.20/001.html"
	,1 => "http://192.168.0.20/002.html"
	,2 => "http://192.168.0.20/003.html"

);

$listDom = array(
	0 => "div#totalArea div.totalinfo"
	,1 => "div#totalArea div.totalinfo"
	,2 => "table.m-table01 tr"

);

$titleDom = array(
	0 => "div.eachStoryText h4"
	,1 => "div.eachStoryText h4"
	,2 => "td.table01__td01 a span.table01__txt01"

);

$urlDom = array(
	0 => "div.eachStoryBtn a"
	,1 => "div.eachStoryBtn a"
	,2 => "td.table01__td01 a"

);






foreach($urlList as $comicId => $comicUrl){
	if(!$comicUrl)	break;
	
	if(!$getHtmlDom	= file_get_html($comicUrl))	break;
	print "<br>----<br>";
	
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


?>
