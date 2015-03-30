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
	
	//HTMLテンプレートファイル名
	const HTML_TAMPLATE = 'tmpl_list.txt';
	
	//HTMLテンプレート埋め込み位置記号
	const HTML_TAMPLATE_INSERT_MARK = '##RSSLIST##';
	
	
	/* --------------------------------------------------------
		コンストラクタ
	-------------------------------------------------------- */
	public function __construct(){
		$this -> logger = Logger::getLogger('DebugLogger');
		//DBクラス
		$this -> db = new DataBaseModel;
		
	}
	
	/* --------------------------------------------------------
		RSS作成
	-------------------------------------------------------- */
	public function startCrawl(){
		
		$this -> logger -> debug('---------- start startCrawl()');
		
		//Goutte
		$goutteConfig	= array('useragent'	=> USER_AGENT
								,'timeout'	=> 10
								);
		$client			= new Client($goutteConfig);

		//サイトデータ取得
		//Select クエリ
		$selectSql	  = "T1.id, T1.url, T1.name, T1.rss_file_name, T1.thum";
		$selectSql	 .= ", T2.dom_upd_list, T2.dom_upd_title, T2.dom_upd_url, T2.dom_upd_date, T2.dom_thum, T2.is_descending";
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
			
			if(!$ret["url"])	continue;
			
			//URLからデータ取得
			try{
				if(!$crawler = $client->request('GET', $ret["url"])){
					//データ取得できない
					$this -> logger -> debug("no get url / " . $ret["name"] . " / " . $ret["url"]);
					continue;
				}
			}catch(Exception $e) {
				//エラー
				$this -> logger -> debug("ERROR " . __LINE__ . " / {$ret["id"]}:{$ret["name"]} / " . $e->getMessage());
				continue;
			}
			
			//取得情報
			$crawlerGetList	= $crawler -> filter($ret["dom_upd_list"]) -> each(
				function($element, $i) use (&$ret){
					
					try{
						//最大クロール数以下のみ処理
						if($i <  CRAWL_STORY_MAX){
							
							$getTitle	= trim($element -> filter($ret["dom_upd_title"]) -> text());
							$getUrl		= trim($element -> filter($ret["dom_upd_url"]) -> attr('href'));
							
							$getDate	= NULL;
							if($ret["dom_upd_date"]) $getDate	=  trim($element -> filter($ret["dom_upd_date"]) -> text());
							
							$getThum	= NULL;
							if($ret["dom_thum"]) $getThum	=  trim($element -> filter($ret["dom_thum"]) -> attr('src'));
							
							return array("title" => $getTitle, "url" => $getUrl,  "dt" => $getDate,  "thum" => $getThum);
						
						}
						
					}catch(Exception $e) {
						//エラー
						//die($e->getMessage());
						$this -> logger -> debug("ERROR " . __LINE__ . " /  {$ret["id"]}:{$ret["name"]} / " . $e->getMessage());
						return false;
					}
				}
			);
			
			
			//リストが昇順の場合、新 → 旧のソート順位変更
			if($ret["is_descending"] == "0")	$crawlerGetList = array_reverse($crawlerGetList);

			//データ整形と 新データのみ取り込み
			$getComicUpdList	= array();
			foreach($crawlerGetList as $tmpValue){
				if(!$tmpValue)	break;
				
				
				//--- タイトル整形
				$tmpValue["title"] = $ret["name"] ." : ". $tmpValue["title"];
				
				//--- リンク整形
				//コミックディレクトリが設定してある場合は 前半に追加
				if($ret["comic_dir"]){
					$tmpValue["url"] = $ret["comic_dir"] . $tmpValue["url"];
				
				//リンクがhttp以外から始まる場合は、サイトURLを追加
				}elseif(substr($tmpValue["url"], 0, 4) != "http"){
					$tmpValue["url"] = $ret["url"] . $tmpValue["url"];
				
				}
				
				//--- 更新日整形
				if($tmpValue["dt"]){
					if(preg_match( '/([0-9]{4})\.([0-9]{2})\.([0-9]{2})/', $tmpValue["dt"], $matches)){
						$tmpValue["dt"]	= $matches[1] ."-". $matches[2] ."-". $matches[3];
					}
				}else{
					$tmpValue["dt"] = date("Y-m-d H:i:s");
				}
				
				//---最終更新と比較 同じであればループ抜ける
				if($ret["last_url"] == $tmpValue["url"])	break;
				
				
				
				//サムネイル画像整形
				//ページから取得した場合
				if($tmpValue["thum"]){
					//1文字目が /なら サイトURL追加
					if(substr($tmpValue["thum"], 0, 1) == "/")	$tmpValue["thum"] =  $ret["site_url"] . $tmpValue["thum"];
				
				// ページから取得出来なかったら 漫画DBに設定されている画像
				}else{
					$tmpValue["thum"]	= $ret["thum"];
				}
				
				$getComicUpdList[] = $tmpValue;
			}
			
			
			//取得情報が無い場合は 次へ
			if(count($getComicUpdList) == 0)	continue;
			
			
			//配列の後ろからデータ取得し、DBに取得情報保持
			$length	= count($getComicUpdList);
			for ($i = $length -1; $i >= 0; $i--) {
				$this -> db -> ins("rss", 
									array(
										 "comic_id"	=> $ret["id"]
										,"title"	=> $getComicUpdList[$i]["title"]
										,"url"		=> $getComicUpdList[$i]["url"]
										,"thum"		=> $getComicUpdList[$i]["thum"]
										,"upd"		=> $getComicUpdList[$i]["dt"]
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
								, "http://54.64.139.63/"
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
	/* --------------------------------------------------------
		DBからHTML作成
	-------------------------------------------------------- */
	private function databaseToHtml(){
		$html = "";

		//サイトデータ取得
		//Select クエリ
		$selectSql	  = "T1.url, T1.name, T1.rss_file_name, T1.thum";
		$selectSql	 .= ", T2.name AS site_name, T2.url AS site_url";
		$selectSql	 .= ", DATE_FORMAT(T3.upd, '%Y/%m/%d') AS last_upd";
		
		//Join クエリ
		$joinSql	 = "comic AS T1";
		$joinSql	.= " INNER JOIN site AS T2 ON T1.site_id = T2.id";
		$joinSql	.= " LEFT JOIN rss AS T3 ON T1.rss_id = T3.id";
		
		//Where クエリ
		$whereSql	= "T1.is_disabled = 0";
		
		//OrderBy クエリ
		$orderSql	= "T3.upd DESC";
		
		
		//DBから リストデータ取得
		$siteStmt  = $this -> db -> findAll($joinSql, $selectSql, $whereSql, $orderSql);
		while($ret = $siteStmt -> fetch(PDO::FETCH_ASSOC)){
$html .= <<<EOF

			<div class="col-sm-4 col-md-2">
				<div class="thumbnail">
					<img alt="" src="{$ret["thum"]}" width="130">
					<div class="caption">
						<h3 id="thumbnail-label"><a href="{$ret["url"]}">{$ret["name"]}</a></h3>
						<p>{$ret["site_name"]}
						<br>update:{$ret["last_upd"]}</p>
						<p><a href="rss/{$ret["rss_file_name"]}.xml" class="btn btn-primary" role="button">RSS</a></p>
					</div>
				</div>
			</div>
EOF;
		}
		
		//テンプレートファイル読み込み
		$templateContents	= file_get_contents(dirname(__FILE__) . '/' . self::HTML_TAMPLATE);
		
		//置換
		$templateContents	= str_replace(self::HTML_TAMPLATE_INSERT_MARK, $html, $templateContents);
		
		//出力
		//file_put_contents ("list.html", $templateContents);
	}
}
?>