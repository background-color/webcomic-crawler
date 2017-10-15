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
		//DBクラス
		$this -> db = new DataBaseModel;

	}

	/* --------------------------------------------------------
  クローラー
	-------------------------------------------------------- */
	public function startCrawl(){

		$this->logger->debug('---------- start startCrawl()');
    $today = date('Y/m/d');

		//Goutte
		$goutteConfig	= array('useragent'	=> USER_AGENT
								,'timeout'	=> 10
								);
		$client			= new Client($goutteConfig);

    $query = <<<SQL
select
c.id, c.name, c.url,
s.check_dom, s.upd_dom,
r.check_text
from comic as c
inner join site as s on c.site_id = s.id
left join (select comic_id, max(id) as id from rss group by comic_id) as r_max on c.id = r_max.comic_id
left join rss as r on r_max.id = r.id
where c.is_disabled = 0
SQL;

		$siteStmt  = $this->db->query($query);
		while($ret = $siteStmt -> fetch(PDO::FETCH_ASSOC)){

			if(!$ret["url"])	continue;
			//$this->logger->debug("{$ret["id"]} {$ret["name"]}");

			try{
				if(!$crawler = $client->request('GET', $ret["url"])){
					$this->logger->debug("no get url / " . $ret["name"] . " / " . $ret["url"]);
					continue;
				}
			}catch(Exception $e) {
				//エラー
				$this->logger->debug("ERROR " . __LINE__ . " / {$ret["id"]}:{$ret["name"]} / " . $e->getMessage());
				continue;
			}

      $chk_text = $crawler -> filter($ret["check_dom"]) -> text();
      $chk_text = trim($chk_text);
      $upd_text = ($ret["upd_dom"]) ? $crawler -> filter($ret["upd_dom"]) -> text() : date('Y/m/d');

      // チェック項目が変わっていたら db追加
      if($ret['check_text'] != $chk_text){
			  $this->logger->debug("update: comic_id:{$ret["id"]} update: {$chk_text}");
        $this->db->ins("rss",
          array(
						"comic_id"  	=> $ret["id"],
						"check_text"	=> $chk_text,
						"upd_text"		=> $upd_text
          ));
      }
			//メモリクリア
			unset($crawler);
    }

    //RSS出力
    $this -> databaseToRss("all.xml", "ALL RSS", 30);
		$this -> logger -> debug('---------- end startCrawl()');
		//メモリクリア
      unset($client);
      return true;
	}

	/* --------------------------------------------------------
		DBからRSS作成
		$fileName	= RSS出力ファイル
		$rssTitle	= RSSタイトル
		$rssCount	= RSSItem数
	-------------------------------------------------------- */
	private function databaseToRss($fileName, $rssTitle,  $rssCount){

		$feedFile	= "rss/" . $fileName;

		$feed = new RSS2;
		$feed -> setTitle($rssTitle);
		$feed -> setLink('rss.background-color.jp');
		$feed -> setDescription("WEB COMIC RSS");
		$feed -> setChannelElement('language', 'ja-JP');

		$feed -> setDate(date(DATE_RSS, time()));
		$feed -> setChannelElement('pubDate', date(\DATE_RSS, strtotime('2013-04-06')));
		$feed -> setSelfLink(HOME_URL . $feedFile);

		//テーブルから指定分取得
		$rssStmt  = $this -> db -> findAll("rss AS T1 INNER JOIN comic AS T2 ON T1.comic_id = T2.id"
											, "T1.id, T1.check_text, T1.upd_text, T2.name, T2.url, T1.ins", "", "T1.id DESC", $rssCount);
		while($rssRet = $rssStmt -> fetch(PDO::FETCH_ASSOC)){

			//RSSのItem出力
			$newItem = $feed -> createNewItem();

			$newItem -> setTitle($rssRet["name"]);
 			$newItem -> setLink($rssRet["url"]);
 			$newItem -> setId($rssRet["id"]);
 		  $newItem -> setDescription($rssRet["check_text"] ."<br>". $rssRet["upd_text"]);

 			$newItem -> setDate($rssRet["ins"]);
			$newItem -> setId($rssRet["url"], true);

			$feed -> addItem($newItem);
		}

		$xml = $feed -> generateFeed();	
		file_put_contents( HOME_PATH . $feedFile , $xml);
		$this->logger->debug('output: ' . HOME_PATH . $feedFile);
		return true;
	}
}
?>
