<?php
/* --------------------------------------------------------
 クローラーテスト
-------------------------------------------------------- */
require_once dirname(__FILE__) . '/library/include.php';
//Goutte(HTML Dom)
require_once LIB_PATH . 'Goutte/goutte.phar';
use Goutte\Client;

$webUrl	= "";
$domList= "";
$domTit	= "";
$domUrl	= "";
$domThm	= "";
$html	= "";
	
if($_POST){
	$webUrl		= $_POST["url"];
	$domList	= $_POST["dlist"];
	$domTit		= $_POST["dtit"];
	$domUrl		= $_POST["durl"];
	$domThm		= $_POST["dthm"];
}

print <<<EOD
<html>
<body>

<br>DOM設定 テストです。
<br>
<form action="test.php" method="post" style="line-height:1.5">

<br>URL <input type="text" name="url" size="80" value="{$webUrl}">
<br>DOM 設定項目
<br>LIT <input type="text" name="dlist" size="80" value="{$domList}">
<br>TIT <input type="text" name="dtit" size="80" value="{$domTit}">
<br>URL <input type="text" name="durl" size="80" value="{$domUrl}">
<br>THUM <input type="text" name="dthm" size="50" value="{$domThm}">

<br>
<br><center><input type="submit" value="　更新　" style="height:2em"></center>
</form>
<br>
EOD;


switch($webUrl && $domList && $domUrl){
	case true : 
		$client = new Client();
		$client->setHeader('User-Agent',USER_AGENT );

		if(!$crawler = $client->request('GET',$webUrl)){
			$html = "URLに繋がらない";
			break;
		}
		
		print "<br>-----";
		print "<br><xmp>" . $crawler -> filter($domList) -> html();
		print "</xmp><br>-----";
		
		$rtnList = $crawler -> filter($domList) -> each(
			function($element, $i) use (&$domTit, &$domUrl, &$domThm, &$html){
				
				
				try{
					$title	= $element -> filter($domTit) -> text();
					$url	= $element -> filter($domUrl) -> attr('href');
					$thum	= ($domThm) ? $element -> filter($domThm) -> attr('src') : "";
					
					return array( $title, $url, $thum);
					
				}catch(Exception $e) {
					//die($e->getMessage());
					return false;
				}
				
			}
		);
		
		foreach($rtnList as $rtn){
			$html .= "<br><br>" . $rtn[0];
			$html .= "<br>" . $rtn[1];
			$html .= "<br>" . $rtn[2];
		
		}
		
		break;
}

print <<<EOD
<br>{$html}
</body>
</html>
EOD;

?>
