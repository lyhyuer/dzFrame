<?php
/* v1.0 2015.10.1 lyhyuer@qq.com */
if(!defined('IN_DZ_FRAME')) {
	exit('Access Denied');
}

class db_driver_mysql
{
	var $tablepre;
	var $version = '';
	var $drivertype = 'mysqli';
	var $querynum = 0;
	var $slaveid = 0;
	var $curlink;
	var $link = array();
	var $config = array();
	var $sqldebug = array();
	var $map = array();

	function db_mysql($config = array()) {
		if(!empty($config)) {
			$this->set_config($config);
		}
	}

	function set_config($config) {
		$this->config = &$config;
	}

	function connect() {

		if(empty($this->config)) {
			$this->halt('config_db_not_found');
		}

			$this->link = $this->_dbconnect(
			$this->config['dbhost'],
			$this->config['dbuser'],
			$this->config['dbpw'],
			$this->config['dbcharset'],
			$this->config['dbname'],
			$this->config['pconnect']
			);
		$this->curlink = $this->link;

	}

	function _dbconnect($dbhost, $dbuser, $dbpw, $dbcharset, $dbname, $pconnect, $halt = true) {

		$link = new mysqli();
		if(!$link->real_connect($dbhost, $dbuser, $dbpw, $dbname, null, null, MYSQLI_CLIENT_COMPRESS)) {
			$halt && $this->halt('notconnect', $this->errno());
		} else {
			$this->curlink = $link;
			if($this->version() > '4.1') {
				$link->set_charset($dbcharset ? $dbcharset : $this->config['dbcharset']);
				$serverset = $this->version() > '5.0.1' ? 'sql_mode=\'\'' : '';
				$serverset && $link->query("SET $serverset");
			}
		}
		return $link;
	}
	
	function table_name($tablename) {
		return $tablename;
	}
	
	function select_db($dbname) {
		return $this->curlink->select_db($dbname);
	}

	function fetch_array($query, $result_type = MYSQLI_ASSOC) {
		if($result_type == 'MYSQL_ASSOC') $result_type = MYSQLI_ASSOC;
		return $query ? $query->fetch_array($result_type) : null;
	}
	
	function fetch_first($sql) {
		return $this->fetch_array($this->query($sql));
	}

	function result_first($sql) {
		return $this->result($this->query($sql), 0);
	}

	public function query($sql, $silent = false, $unbuffered = false) {
		if(defined('DEBUG') && DEBUG) {
			$starttime = microtime(true);
		}

		if('UNBUFFERED' === $silent) {
			$silent = false;
			$unbuffered = true;
		} elseif('SILENT' === $silent) {
			$silent = true;
			$unbuffered = false;
		}
		$resultmode = $unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT;
		if(!($query = $this->curlink->query($sql, $resultmode))) {
			if(in_array($this->errno(), array(2006, 2013)) && substr($silent, 0, 5) != 'RETRY') {
				$this->connect();
				return $this->curlink->query($sql, 'RETRY'.$silent);
			}
			if(!$silent) {
				$this->halt($this->error(), $this->errno(), $sql);
			}
		}

		if(defined('DEBUG') && DEBUG) {
			$this->sqldebug[] = array($sql, number_format((microtime(true) - $starttime), 6), debug_backtrace(), $this->curlink);
		}

		$this->querynum++;
		return $query;
	}	

	function affected_rows() {
		return $this->curlink->affected_rows;
	}

	function error() {
		return (($this->curlink) ? $this->curlink->error : mysqli_error());
	}

	function errno() {
		return intval(($this->curlink) ? $this->curlink->errno : mysqli_errno());
	}

	function result($query, $row = 0) {
		if(!$query || $query->num_rows == 0) {
			return null;
		}
		$query->data_seek($row);
		$assocs = $query->fetch_row();
		return $assocs[0];
	}

	function num_rows($query) {
		$query = $query ? $query->num_rows : 0;
		return $query;
	}

	function num_fields($query) {
		return $query ? $query->field_count : null;
	}

	function free_result($query) {
		return $query ? $query->free() : false;
	}

	function insert_id() {
		return ($id = $this->curlink->insert_id) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	function fetch_row($query) {
		$query = $query ? $query->fetch_row() : null;
		return $query;
	}

	function fetch_fields($query) {
		return $query ? $query->fetch_field() : null;
	}

	function version() {
		if(empty($this->version)) {
			$this->version = $this->curlink->server_info;
		}
		return $this->version;
	}

	function escape_string($str) {
		return $this->curlink->escape_string($str);
	}

	function close() {
		return $this->curlink->close();
	}

	function halt($message = '', $code = 0, $sql = '') {
		throw new DbException($message, $code, $sql);
	}

}


?>