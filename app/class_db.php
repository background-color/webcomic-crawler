<?php
/* --------------------------------------------------------
 DB Class 2014/12/18
-------------------------------------------------------- */
class DataBaseModel {
	private $db;
	private $logger;

	/* --------------------------------------------------------
		コンストラクタ
	-------------------------------------------------------- */
	public function __construct(){
		$this -> dbconnect();
		$this -> logger = Logger::getLogger('DebugLogger');
	}

	/* --------------------------------------------------------
		DB接続
	-------------------------------------------------------- */
	private function dbconnect(){

		try {
			$this -> db = new PDO(
				"mysql:dbname=" . DB_NAME . ";host=" . DB_HOST . ";charset=utf8",
				DB_USER,
				DB_PASS,
				array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
				);
		}catch(PDOException $e) {
				die($e->getMessage());
		}
	}

	/* --------------------------------------------------------
		クエリ文 SELECT
	-------------------------------------------------------- */
	public function findAll($table, $select = "*", $where = false, $orderby = false, $limit = false){
		$query = "SELECT {$select} FROM {$table}";

		if($where)		$query .= " WHERE {$where}";
		if($orderby)	$query .= " ORDER BY {$orderby}";
		if($limit)		$query .= " LIMIT 0, {$limit}";

		try {
			$stmt =  $this -> db -> query($query);
		}catch(PDOException $e) {
			die($e->getMessage());
		}
		return $stmt;
	}
	/* --------------------------------------------------------
		クエリ文 INSERT
		$table		= テーブル名
		$insList	= array( 0 => array(カラム名 => 内容)) の配列
	-------------------------------------------------------- */
	public function ins($table, $insList){
		if(!$insList || !$table)	return;

		$columnList		= array();
		$prepareList	= array();

		foreach($insList as $column => $value){
			$columnList[]	= $column;
			$prepareList[]	= ":" . $column;
		}

		$insSql	 = "INSERT INTO {$table} (";
		$insSql	.= implode(",", $columnList);
		$insSql	.= ", ins) VALUES (";
		$insSql	.= implode(",", $prepareList);
		$insSql	.= ", now())";

		$stmt = $this->db->prepare($insSql);

		foreach($insList as $column => $value){
			$stmt->bindValue(":" . $column, $value);
		}

		try {
			$stmt -> execute();
		}catch(PDOException $e) {
			die($e->getMessage());
		}
		return true;


	}
	/* --------------------------------------------------------
		クエリ文 UPDATE
		$table		= テーブル名
		$updList	= array( 0 => array(カラム名 => 内容)) の配列
		$where		= WHEREクエリ文
	-------------------------------------------------------- */
	public function upd($table, $updList, $where){
		if(!$updList || !$table || !$where)	return;

		$setSqlList		= array();

		foreach($updList as $column => $value){
			$setSqlList[]	= "{$column} =  :{$column}";
		}
		
		$updSql	 = "UPDATE {$table} SET ";
		$updSql	.= implode(",", $setSqlList);
		$updSql	.= " WHERE " . $where;
		
		//$this -> logger -> debug('SQL: ' . $updSql);
		
		$stmt = $this -> db -> prepare($updSql);
		
		foreach($updList as $column => $value){
			$stmt->bindValue(":" . $column, $value);
		}
		
		try {
			$stmt -> execute();
		}catch(PDOException $e) {
			die($e->getMessage());
		}
		return true;
		
	
	}
	/* --------------------------------------------------------
		INSERT ID取得
	-------------------------------------------------------- */
	public function lastInsertId(){
		return $this -> db -> lastInsertId();
	}
	
	
	function DataBase($db=DB_NAME, $host=DB_HOST, $user=DB_USER, $pass=DB_PW){
		if($this->conid){
			return true;
		}else{
			@$this->conid = mysql_connect($host, $user, $pass);
			if ($this->conid) {
				mysql_set_charset("utf8");
				if(mysql_select_db($db)) return true;
			}
			die("DataBase Connect Error!<br>" . mysql_errno() . ": " . mysql_error());
			return false;
		}
		
		if($GLOBALS['DEBUG'])	$this->debugFlg = true;
	}
	function close(){
		 mysql_close($this->conid);
	}
	function begin(){
		mysql_query("START TRANSACTION") ? true : false;
	}
	function rollback(){
		mysql_query("ROLLBACK") ? true : false;
	}
	function commit(){
		mysql_query("COMMIT") ? true : false;
	}
	function getErr(){
		return $this->err;
	}
	// ----------------------------------------------------
	// クエリ処理 // 単純実行
	// ----------------------------------------------------
	function query($query){
		try {
			$stmt =  $this->db->query($query);
		}catch(PDOException $e) {
			die($e->getMessage());
		}
    return $stmt;
	}
}
?>
