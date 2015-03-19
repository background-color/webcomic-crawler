<?php
/* --------------------------------------------------------
 Wwb Comic Rss Class 2014/12/18 
-------------------------------------------------------- */
//Goutte(HTML Dom)
require_once LIB_PATH . 'Goutte/goutte.phar';
use Goutte\Client;

//PHP Universal Feed Generator
require_once LIB_PATH . 'FeedWriter-master/Item.php';
require_once LIB_PATH . 'FeedWriter-master/Feed.php';
require_once LIB_PATH . 'FeedWriter-master/RSS2.php';
date_default_timezone_set("Asia/Tokyo");
use \FeedWriter\RSS2;

class WebComicRss {
	//DBクラス
	private $db;
	//FEEDクラス
	private $feed;
	//ログクラス
	private $logger;
	
	
	/* --------------------------------------------------------
		コンストラクタ
	-------------------------------------------------------- */
	public function __construct(){
		$this -> logger = Logger::getLogger('DebugLogger');
		
	}
	
	/* --------------------------------------------------------
		RSS作成
	-------------------------------------------------------- */
	public function startCrawl(){
		
		$this -> logger -> debug('---------- start startCrawl()');

		//DBクラス
		$this -> db = new DataBaseModel;
		
		//Goutte
		$client = new Client();

		//サイトデータ取得
		//Select クエリ
		$selectSql	  = "T1.id, T1.url, T1.name, T1.rss_file_name, T1.thum";
		$selectSql	 .= ", T2.dom_upd_list, T2.dom_upd_title, T2.dom_upd_url, T2.dom_thum, T2.is_descending";
		$selectSql	 .= ", T2.url AS site_url, T2.comic_dir";
		$selectSql	 .= ", T3.title AS last_title, T3.url AS last_url";
		
		//Join クエリ
		$joinSql	 = "comic AS T1";
		$joinSql	.= " INNER JOIN site AS T2 ON T1.site_id = T2.id";
		$joinSql	.= " LEFT JOIN rss AS T3 ON T1.rss_id = T3.id";
		
		//Where クエリ
		$whereSql	= "T1.is_disabled = 0";
		
		
		//DBから 取得
		$siteStmt  = $this -> db -> findAll($joinSql, $selectSql, $whereSql);
		while($ret = $siteStmt -> fetch(PDO::FETCH_ASSOC)){
			$this -> logger -> debug("get " . $ret["name"]);
			
			if(!$ret["url"])	continue;
			
			if(!$crawler = $client->request('GET', $ret["url"])){
				//データ取得できない
				$this -> logger -> debug("no get url / " . $ret["name"] . " / " . $ret["url"]);
				continue;
			}
			
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
							$getThum	= NULL;
							if($ret["dom_thum"]) $getThum	=  trim($element -> filter($ret["dom_thum"]) -> attr('src'));
						
							//最終更新と比較 異なれば 値を返す
							if($ret["last_title"] != $getTitle && $ret["last_url"] != $getUrl){
								return array($getTitle, $getUrl, $getThum);
							
							//一緒であれば 更新フラグ OFF
							}else{
								$isComicUpd = false;
							}
						}
						
					}catch(Exception $e) {
						//エラー
						//die($e->getMessage());
						$this -> logger -> debug($e->getMessage());
						return false;
					}
				}
			);
			
			
			//取得情報が無い場合は 次へ
			if(count($getComicUpdList) == 0)	continue;

			//空を削除して 
			$tmpComicUpdList = $getComicUpdList;
			$getComicUpdList	= array();
			foreach($tmpComicUpdList as $tmpComicUpd){
				if($tmpComicUpd){
					
					//タイトル整形
					$tmpComicUpd[1] = $ret["name"] ." : ". $tmpComicUpd[1];
					
					//リンク整形
					//コミックディレクトリが設定してある場合は 前半に追加、URLデコード
					if($ret["comic_dir"]){
						$tmpComicUpd[1] = $ret["comic_dir"] . $tmpComicUpd[1];
					
					//リンクが#から始まる場合は、サイトURLを追加
					}elseif(substr($tmpComicUpd[1], 0, 1) == "#"){
						$tmpComicUpd[1] = $ret["url"] . $tmpComicUpd[1];
					
					}
					
					//サムネイル画像整形
					//ページから取得した場合
	 				if($tmpComicUpd[2]){
	 					//1文字目が /なら サイトURL追加
	 					if(substr($tmpComicUpd[2], 0, 1) == "/")	$tmpComicUpd[2] =  $ret["site_url"] . $tmpComicUpd[2];
	 				
	 				// ページから取得出来なかったら 漫画DBに設定されている画像
	 				}else{
	 					$tmpComicUpd[2]	= $ret["thum"];
	 				}
	 				
					
					//降順リスト 前に追加。順番を古 → 新に変換
					if($ret["is_descending"] == "1"){
						array_unshift($getComicUpdList, $tmpComicUpd);
						
					//昇順リスト 後ろに追加。順番は変えない
					}else{
						array_push($getComicUpdList, $tmpComicUpd);
					}
				}
			}
			
			
			
			//DBに取得情報保持
			foreach($getComicUpdList as $getComicUpd){
				$this -> db -> ins("rss", 
									array(
										 "comic_id"	=> $ret["id"]
										,"title"	=> $getComicUpd[0]
										,"url"		=> $getComicUpd[1]
										,"thum"		=> $getComicUpd[2]
									));
			}
			
			//最後に更新した rss.id取得
			$rssId	= $this -> db ->lastInsertId();
			
			//comicテーブル更新
			$this -> db -> upd("comic"
								,array("rss_id"	=> $this -> db ->lastInsertId())
								,"id = {$ret["id"]}");
			
			//メモリクリア
			unset($crawler);
			
			//RSS出力
			$this -> databaseToRss($ret["rss_file_name"] . ".xml"
								, $ret["name"]
								, $ret["url"]
								,  RSS_ITEM_MAX
								, "comic_id = {$ret["id"]}");

		}
		
		
		//全体から 最新30件RSS出力
		$this -> databaseToRss("all.xml"
								, "ALL RSS"
								, "http://54.64.139.63"
								, 30
								, "");
		
		
		
		
		$this -> logger -> debug('---------- end startCrawl()');
		
		//メモリクリア
		unset($client);
		return true;

	}
	
	/* --------------------------------------------------------
		DBからRSS作成
		$fileName	= RSS出力ファイル
		$rssTitle	= RSSタイトル
		$rssUrl		= RSSURL
		$rssCount	= RSSItem数
		$queryWhere	= RSS出力データ where 句
	-------------------------------------------------------- */
	private function databaseToRss($fileName, $rssTitle, $rssUrl, $rssCount, $queryWhere){
			
		$feedFile	= "rss/" . $fileName;
		
		$feed = new RSS2;
		$feed -> setTitle($rssTitle);
		$feed -> setLink($rssUrl);
		$feed -> setDescription("WEB COMIC RSS");
		$feed -> setChannelElement('language', 'ja-JP');
		
		$feed -> setDate(date(DATE_RSS, time()));
		$feed -> setChannelElement('pubDate', date(\DATE_RSS, strtotime('2013-04-06')));
		$feed -> setSelfLink(HOME_URL . $feedFile);
		
		//テーブルから指定分取得
		$rssStmt  = $this -> db -> findAll("rss", "title, url, upd, thum", $queryWhere, "id DESC", $rssCount);
		while($rssRet = $rssStmt -> fetch(PDO::FETCH_ASSOC)){
			
			//RSSのItem出力
			$newItem = $feed -> createNewItem();
			
			$newItem -> setTitle($rssRet["title"]);
 			$newItem -> setLink($rssRet["url"]);
 			
 			if($rssRet["thum"]){
 				$newItem -> setDescription("<img src={$rssRet["thum"]}>");
 			}else{
 				$newItem -> setDescription("");
 			}
 			
 			$newItem -> setDate($rssRet["upd"]);
			$newItem -> setId($rssRet["url"], true);
			
			$feed -> addItem($newItem);
		}
		
		$xml = $feed -> generateFeed();	
		file_put_contents( HOME_PATH . $feedFile , $xml);
		
		return true;
	}
}
?>