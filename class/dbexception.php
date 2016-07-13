<?php
/* v1.0 2015.10.1 lyhyuer@qq.com */

if(!defined('IN_DZ_FRAME')) {
	exit('Access Denied');
}

class DbException extends Exception{

	public $sql;

	public function __construct($message, $code = 0, $sql = '') {
		$this->sql = $sql;
		parent::__construct($message, $code);
	}

	public function getSql() {
		return $this->sql;
	}
}
?>