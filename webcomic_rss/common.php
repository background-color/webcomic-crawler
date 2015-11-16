<?php
/* --------------------------------------------------------
 エラー処理 
-------------------------------------------------------- */
set_error_handler(function($errno, $errstr, $errfile, $errline) {
	 // エラーを例外に変換する
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function($e) {
	// display_errorsの値によって処理を変更する
	if (ini_get('display_errors')) {
		echo '<pre>' . $e . '</pre>';
	} else {
		// エラーログに保存なりなんなりしてエラー画面表示
		// ...
		//readfile('path/to/error.html');
	}
});
?>