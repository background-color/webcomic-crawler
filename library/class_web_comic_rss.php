<?php
/* --------------------------------------------------------
 Wwb Comic Rss Class 2014/12/18 
-------------------------------------------------------- */
//Goutte(HTML Dom)
require_once HOME_PATH . 'library/Goutte/goutte.phar';
use Goutte\Client;

//PHP Universal Feed Generator
require_once HOME_PATH . 'library/FeedWriter-master/Item.php';
require_once HOME_PATH . 'library/FeedWriter-master/Feed.php';
require_once HOME_PATH . 'library/FeedWriter-master/RSS2.php';
date_default_timezone_set("Asia/Tokyo");
use \FeedWriter\RSS2;

class WebComicRss {
	private $db;
	private $feed;
	
	/* --------------------------------------------------------
		コンストラクタ
	-------------------------------------------------------- */
	public function __construct(){
	}
	
	/* --------------------------------------------------------
		RSS作成
	-------------------------------------------------------- */
	public function startCrawl(){
		
		//DBクラス
		$this -> db = new DataBaseModel;
		
		//Goutte
		$client = new Client();

		//サイトデータ取得
		//Select クエリ
		$selectSql	  = "T1.id, T1.url, T1.name, T1.rss_file_name, T1.upd_chk_title";
		$selectSql	 .= ", T2.dom_upd_list, T2.dom_upd_title, T2.dom_upd_url";
		$selectSql	 .= ", T3.title AS last_title, T3.url AS last_url";
		
		//Join クエリ
		$joinSql	 = "comic AS T1";
		$joinSql	.= " INNER JOIN site AS T2 ON T1.site_id = T2.id";
		$joinSql	.= " LEFT JOIN rss AS T3 ON T1.rss_id = T3.id";
		
		//DBから 取得
		$siteStmt  = $this -> db -> findAll($joinSql, $selectSql);
		while($ret = $siteStmt -> fetch(PDO::FETCH_ASSOC)){

			if(!$ret["url"])	continue;
			
			if(!$crawler = $client->request('GET', $ret["url"])){
				//HTML取得できなかった エラー処理 入れる
				continue;
			}
			
			//URLパース取得しておく
			$urlPaparse = parse_url($ret["url"]);
			
			//更新チェックフラグ
			$isComicUpd	= true;
			
			//取得情報
			$getComicUpdList	= $crawler -> filter($ret["dom_upd_list"]) -> each(
				function($element, $i) use (&$ret, &$isComicUpd){
					try{
						//最大クロール数以下のみ処理
						if($i <  CRAWL_STORY_MAX && $isComicUpd){
							
							$getTitle	= trim($element -> filter($ret["dom_upd_title"]) -> text());
							$getUrl		= trim($element -> filter($ret["dom_upd_url"]) -> attr('href'));
							
							//タイトルチェック
							if($ret["upd_chk_title"]){
								//タイトル異なる場合 スルー
								if (strpos($getTitle, $ret["upd_chk_title"]) === FALSE)	return false;
							}
						
							//最終更新と比較 異なれば 値を返す
							if($ret["last_title"] != $getTitle && $ret["last_url"] != $getUrl){
								return array($getTitle, $getUrl);
							
							//一緒であれば 更新フラグ OFF
							}else{
								$isComicUpd = false;
							}
						}
					}catch(Exception $e) {
						// エラー処理入れるか？
						//die($e->getMessage());
						return false;
					}
				}
			);
			

			//空を削除して 順番を古 → 新
			$tmpComicUpdList = $getComicUpdList;
			$getComicUpdList	= array();
			foreach($tmpComicUpdList as $tmpComicUpd){
				if($tmpComicUpd){
				
					//URLが /からはじまっている場合 ドメインを足す
					if( substr($tmpComicUpd[0], 0, 1) == "/"){
						$tmpComicUpd[0] = $urlPaparse["scheme"] . "://" . $urlPaparse["hostname"] . $tmpComicUpd[0];
					}
					array_unshift($getComicUpdList, $tmpComicUpd);
				}
			}
			
			//取得情報が無い場合は 次へ
			if(count($getComicUpdList) == 0)	continue;
			
			
			
			//DBに取得情報登録
			foreach($getComicUpdList as $getComicUpd){
				$this -> db -> ins("rss", 
									array(
										 "comic_id"	=> $ret["id"]
										,"title"	=> $getComicUpd[0]
										,"url"		=> $getComicUpd[1]
									));
			}
			
			//最後に更新した rss.id取得
			$rssId	= $this -> db ->lastInsertId();
			
			//comicテーブル更新
			$this -> db -> upd("comic"
								,array("rss_id"	=> $this -> db ->lastInsertId())
								,"id = {$ret["id"]}");
			
			
			
			//取得情報を RSS出力
			$feedFile	= "rss/" . $ret["rss_file_name"] . ".xml";
			$feed = new RSS2;
			$feed -> setTitle($ret["name"]);
			$feed -> setLink($ret["url"]);
			$feed -> setDescription("WEB COMIC RSS");
			$feed -> setChannelElement('language', 'ja-JP');
			
			$feed -> setDate(date(DATE_RSS, time()));
			$feed -> setChannelElement('pubDate', date(\DATE_RSS, strtotime('2013-04-06')));
			$feed -> setSelfLink(HOME_URL . $feedFile);

			
			//テーブルから指定分取得
			$rssStmt  = $this -> db -> findAll("rss", "title, url, upd", "comic_id = {$ret["id"]}", "id DESC", RSS_ITEM_MAX);
			while($rssRet = $rssStmt -> fetch(PDO::FETCH_ASSOC)){
		
		
			//foreach($getComicUpdList as $getComicUpd){
				$newItem = $feed -> createNewItem();
				
				$newItem -> setTitle($rssRet["title"]);
				$newItem -> setLink($rssRet["url"]);
				$newItem -> setDescription("");
				
				$newItem -> setDate($rssRet["upd"]);
				$newItem -> setId($rssRet["url"], true);
				
				/*
				$newItem -> setTitle($getComicUpd[0]);
				$newItem -> setLink($getComicUpd[1]);
				$newItem -> setDescription("");
				
				$newItem -> setDate(date("Y-m-d H:i:s"));
				$newItem -> setId($getComicUpd[1], true);
				*/
				
				$feed -> addItem($newItem);
			}
			
			$xml = $feed -> generateFeed();	
			file_put_contents( HOME_PATH . $feedFile , $xml);

			//メモリクリア
			unset($crawler);
		}
		
		//メモリクリア
		unset($client);
		return true;

	}
}
?>