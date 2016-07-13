<?php
/* v1.0 2015.10.1 lyhyuer@qq.com */
if(!defined('IN_DZ_FRAME')) {
	exit('Access Denied');
}

define('DZ_FRAME_CORE_FUNCTION', true);

function durlencode($url) {
	static $fix = array('%21', '%2A','%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D');
	static $replacements = array('!', '*', ';', ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]");
	return str_replace($fix, $replacements, urlencode($url));
}

function system_error($message, $show = true, $save = true, $halt = true) {
	dz_frame_error::system_error($message, $show, $save, $halt);
}

function updatesession() {
	return C::app()->session->updatesession();
}

function setglobal($key , $value, $group = null) {
	global $_G;
	$key = explode('/', $group === null ? $key : $group.'/'.$key);
	$p = &$_G;
	foreach ($key as $k) {
		if(!isset($p[$k]) || !is_array($p[$k])) {
			$p[$k] = array();
		}
		$p = &$p[$k];
	}
	$p = $value;
	return true;
}

function getglobal($key, $group = null) {
	global $_G;
	$key = explode('/', $group === null ? $key : $group.'/'.$key);
	$v = &$_G;
	foreach ($key as $k) {
		if (!isset($v[$k])) {
			return null;
		}
		$v = &$v[$k];
	}
	return $v;
}

function getgpc($k, $type='GP') {
	$type = strtoupper($type);
	switch($type) {
		case 'G': $var = &$_GET; break;
		case 'P': $var = &$_POST; break;
		case 'C': $var = &$_COOKIE; break;
		default:
			if(isset($_GET[$k])) {
				$var = &$_GET;
			} else {
				$var = &$_POST;
			}
			break;
	}

	return isset($var[$k]) ? $var[$k] : NULL;

}

function daddslashes($string, $force = 1) {
	if(is_array($string)) {
		$keys = array_keys($string);
		foreach($keys as $key) {
			$val = $string[$key];
			unset($string[$key]);
			$string[addslashes($key)] = daddslashes($val, $force);
		}
	} else {
		$string = addslashes($string);
	}
	return $string;
}

function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	$ckey_length = 4;
	$key = md5($key != '' ? $key : getglobal('authkey'));
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);

	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);

	$result = '';
	$box = range(0, 255);

	$rndkey = array();
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}

	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}

	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}

	if($operation == 'DECODE') {
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return $keyc.str_replace('=', '', base64_encode($result));
	}

}

function fsocketopen($hostname, $port = 80, &$errno, &$errstr, $timeout = 15) {
	$fp = '';
	if(function_exists('fsockopen')) {
		$fp = @fsockopen($hostname, $port, $errno, $errstr, $timeout);
	} elseif(function_exists('pfsockopen')) {
		$fp = @pfsockopen($hostname, $port, $errno, $errstr, $timeout);
	} elseif(function_exists('stream_socket_client')) {
		$fp = @stream_socket_client($hostname.':'.$port, $errno, $errstr, $timeout);
	}
	return $fp;
}

function dfsockopen($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE, $encodetype  = 'URLENCODE', $allowcurl = TRUE, $position = 0, $files = array()) {
	require_once libfile('function/filesock');
	return _dfsockopen($url, $limit, $post, $cookie, $bysocket, $ip, $timeout, $block, $encodetype, $allowcurl, $position, $files);
}

function dhtmlspecialchars($string, $flags = null) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = dhtmlspecialchars($val, $flags);
		}
	} else {
		if($flags === null) {
			$string = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $string);
			if(strpos($string, '&amp;#') !== false) {
				$string = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $string);
			}
		} else {
			if(PHP_VERSION < '5.4.0') {
				$string = htmlspecialchars($string, $flags);
			} else {
				if(strtolower(CHARSET) == 'utf-8') {
					$charset = 'UTF-8';
				} else {
					$charset = 'ISO-8859-1';
				}
				$string = htmlspecialchars($string, $flags, $charset);
			}
		}
	}
	return $string;
}

function dexit($message = '') {
	echo $message;
	exit();
}

function dheader($string, $replace = true, $http_response_code = 0) {
	$islocation = substr(strtolower(trim($string)), 0, 8) == 'location';
	if(defined('IN_MOBILE') && strpos($string, 'mobile') === false && $islocation) {
		if (strpos($string, '?') === false) {
			$string = $string.'?mobile='.IN_MOBILE;
		} else {
			if(strpos($string, '#') === false) {
				$string = $string.'&mobile='.IN_MOBILE;
			} else {
				$str_arr = explode('#', $string);
				$str_arr[0] = $str_arr[0].'&mobile='.IN_MOBILE;
				$string = implode('#', $str_arr);
			}
		}
	}
	$string = str_replace(array("\r", "\n"), array('', ''), $string);
	if(empty($http_response_code) || PHP_VERSION < '4.3' ) {
		@header($string, $replace);
	} else {
		@header($string, $replace, $http_response_code);
	}
	if($islocation) {
		exit();
	}
}

function dsetcookie($var, $value = '', $life = 0, $prefix = 1, $httponly = false) {

	global $_G;

	$config = $_G['config']['cookie'];

	$_G['cookie'][$var] = $value;
	$var = ($prefix ? $config['cookiepre'] : '').$var;
	$_COOKIE[$var] = $value;

	if($value == '' || $life < 0) {
		$value = '';
		$life = -1;
	}

	if(defined('IN_MOBILE')) {
		$httponly = false;
	}

	$life = $life > 0 ? getglobal('timestamp') + $life : ($life < 0 ? getglobal('timestamp') - 31536000 : 0);
	$path = $httponly && PHP_VERSION < '5.2.0' ? $config['cookiepath'].'; HttpOnly' : $config['cookiepath'];

	$secure = $_SERVER['SERVER_PORT'] == 443 ? 1 : 0;
	if(PHP_VERSION < '5.2.0') {
		setcookie($var, $value, $life, $path, $config['cookiedomain'], $secure);
	} else {
		setcookie($var, $value, $life, $path, $config['cookiedomain'], $secure, $httponly);
	}
}

function getcookie($key) {
	global $_G;
	return isset($_G['cookie'][$key]) ? $_G['cookie'][$key] : '';
}

function fileext($filename) {
	return addslashes(strtolower(substr(strrchr($filename, '.'), 1, 10)));
}

function token($uuid) {
	global $_G;
	return substr(md5(substr($_G['timestamp'], 0, -7).$uuid.$_G['config']['security']['authkey']), 8, 8);
}

function checkrobot($useragent = '') {
	static $kw_spiders = array('bot', 'crawl', 'spider' ,'slurp', 'sohu-search', 'lycos', 'robozilla');
	static $kw_browsers = array('msie', 'netscape', 'opera', 'konqueror', 'mozilla');

	$useragent = strtolower(empty($useragent) ? $_SERVER['HTTP_USER_AGENT'] : $useragent);
	if(strpos($useragent, 'http://') === false && dstrpos($useragent, $kw_browsers)) return false;
	if(dstrpos($useragent, $kw_spiders)) return true;
	return false;
}
function checkmobile() {
	global $_G;
	$mobile = array();
	static $touchbrowser_list =array('iphone', 'android', 'phone', 'mobile', 'wap', 'netfront', 'java', 'opera mobi', 'opera mini',
				'ucweb', 'windows ce', 'symbian', 'series', 'webos', 'sony', 'blackberry', 'dopod', 'nokia', 'samsung',
				'palmsource', 'xda', 'pieplus', 'meizu', 'midp', 'cldc', 'motorola', 'foma', 'docomo', 'up.browser',
				'up.link', 'blazer', 'helio', 'hosin', 'huawei', 'novarra', 'coolpad', 'webos', 'techfaith', 'palmsource',
				'alcatel', 'amoi', 'ktouch', 'nexian', 'ericsson', 'philips', 'sagem', 'wellcom', 'bunjalloo', 'maui', 'smartphone',
				'iemobile', 'spice', 'bird', 'zte-', 'longcos', 'pantech', 'gionee', 'portalmmm', 'jig browser', 'hiptop',
				'benq', 'haier', '^lct', '320x320', '240x320', '176x220');
	static $mobilebrowser_list =array('windows phone');
	static $wmlbrowser_list = array('cect', 'compal', 'ctl', 'lg', 'nec', 'tcl', 'alcatel', 'ericsson', 'bird', 'daxian', 'dbtel', 'eastcom',
			'pantech', 'dopod', 'philips', 'haier', 'konka', 'kejian', 'lenovo', 'benq', 'mot', 'soutec', 'nokia', 'sagem', 'sgh',
			'sed', 'capitel', 'panasonic', 'sonyericsson', 'sharp', 'amoi', 'panda', 'zte');

	$pad_list = array('ipad');

	$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);

	if(dstrpos($useragent, $pad_list)) {
		return false;
	}
	if(($v = dstrpos($useragent, $mobilebrowser_list, true))){
		$_G['mobile'] = $v;
		return '1';
	}
	if(($v = dstrpos($useragent, $touchbrowser_list, true))){
		$_G['mobile'] = $v;
		return '2';
	}
	if(($v = dstrpos($useragent, $wmlbrowser_list))) {
		$_G['mobile'] = $v;
		return '3'; //wml版
	}
	$brower = array('mozilla', 'chrome', 'safari', 'opera', 'm3gate', 'winwap', 'openwave', 'myop');
	if(dstrpos($useragent, $brower)) return false;

	$_G['mobile'] = 'unknown';
	if(isset($_G['mobiletpl'][$_GET['mobile']])) {
		return true;
	} else {
		return false;
	}
}

function dstrpos($string, $arr, $returnvalue = false) {
	if(empty($string)) return false;
	foreach((array)$arr as $v) {
		if(strpos($string, $v) !== false) {
			$return = $returnvalue ? $v : true;
			return $return;
		}
	}
	return false;
}

function isemail($email) {
	return strlen($email) > 6 && strlen($email) <= 32 && preg_match("/^([A-Za-z0-9\-_.+]+)@([A-Za-z0-9\-]+[.][A-Za-z0-9\-.]+)$/", $email);
}

function quescrypt($questionid, $answer) {
	return $questionid > 0 && $answer != '' ? substr(md5($answer.md5($questionid)), 16, 8) : '';
}

function random($length, $numeric = 0) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	if($numeric) {
		$hash = '';
	} else {
		$hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
		$length--;
	}
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed{mt_rand(0, $max)};
	}
	return $hash;
}

function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}


function dsign($str, $length = 16){
	return substr(md5($str.getglobal('config/security/authkey')), 0, ($length ? max(8, $length) : 16));
}

function modauthkey($id) {
	return md5(getglobal('username').getglobal('uid').getglobal('authkey').substr(TIMESTAMP, 0, -7).$id);
}


function dgmdate($timestamp, $format = 'dt', $timeoffset = '9999', $uformat = '') {
	global $_G;
	$format == 'u' && !$_G['setting']['dateconvert'] && $format = 'dt';
	static $dformat, $tformat, $dtformat, $offset, $lang;
	if($dformat === null) {
		$dformat = getglobal('setting/dateformat');
		$tformat = getglobal('setting/timeformat');
		$dtformat = $dformat.' '.$tformat;
		$offset = getglobal('member/timeoffset');
		$sysoffset = getglobal('setting/timeoffset');
		$offset = $offset == 9999 ? ($sysoffset ? $sysoffset : 0) : $offset;
		$lang = lang('core', 'date');
	}
	$timeoffset = $timeoffset == 9999 ? $offset : $timeoffset;
	$timestamp += $timeoffset * 3600;
	$format = empty($format) || $format == 'dt' ? $dtformat : ($format == 'd' ? $dformat : ($format == 't' ? $tformat : $format));
	if($format == 'u') {
		$todaytimestamp = TIMESTAMP - (TIMESTAMP + $timeoffset * 3600) % 86400 + $timeoffset * 3600;
		$s = gmdate(!$uformat ? $dtformat : $uformat, $timestamp);
		$time = TIMESTAMP + $timeoffset * 3600 - $timestamp;
		if($timestamp >= $todaytimestamp) {
			if($time > 3600) {
				$return = intval($time / 3600).'&nbsp;'.$lang['hour'].$lang['before'];
			} elseif($time > 1800) {
				$return = $lang['half'].$lang['hour'].$lang['before'];
			} elseif($time > 60) {
				$return = intval($time / 60).'&nbsp;'.$lang['min'].$lang['before'];
			} elseif($time > 0) {
				$return = $time.'&nbsp;'.$lang['sec'].$lang['before'];
			} elseif($time == 0) {
				$return = $lang['now'];
			} else {
				$return = $s;
			}
			if($time >=0 && !defined('IN_MOBILE')) {
				$return = '<span title="'.$s.'">'.$return.'</span>';
			}
		} elseif(($days = intval(($todaytimestamp - $timestamp) / 86400)) >= 0 && $days < 7) {
			if($days == 0) {
				$return = $lang['yday'].'&nbsp;'.gmdate($tformat, $timestamp);
			} elseif($days == 1) {
				$return = $lang['byday'].'&nbsp;'.gmdate($tformat, $timestamp);
			} else {
				$return = ($days + 1).'&nbsp;'.$lang['day'].$lang['before'];
			}
			if(!defined('IN_MOBILE')) {
				$return = '<span title="'.$s.'">'.$return.'</span>';
			}
		} else {
			$return = $s;
		}
		return $return;
	} else {
		return gmdate($format, $timestamp);
	}
}

function dmktime($date) {
	if(strpos($date, '-')) {
		$time = explode('-', $date);
		return mktime(0, 0, 0, $time[1], $time[2], $time[0]);
	}
	return 0;
}

function dnumber($number) {
	return abs($number) > 10000 ? '<span title="'.$number.'">'.intval($number / 10000).'</span>' : $number;
}

function dimplode($array) {
	if(!empty($array)) {
		$array = array_map('addslashes', $array);
		return "'".implode("','", is_array($array) ? $array : array($array))."'";
	} else {
		return 0;
	}
}

function libfile($libname, $folder = '') {
	$libpath = '/'.$folder;
	if(strstr($libname, '/')) {
		list($pre, $name) = explode('/', $libname);
		$path = "{$libpath}/{$pre}/{$pre}_{$name}";
	} else {
		$path = "{$libpath}/{$libname}";
	}
	return preg_match('/^[\w\d\/_]+$/i', $path) ? realpath(DZ_FRAME_ROOT.$path.'.php') : false;
}

function lang($file, $key) {
	global $_G;
	if(!isset($_G['lang'][$key])) {
		include DZ_FRAME_ROOT.'./class/'.'lang_'.$file.'.php';
		$_G['lang'][$key] = $lang[$key];
	}
	$returnvalue = &$_G['lang'][$key];
	$host=isset($_G["siteurl"])?$_G["siteurl"]:'http://www.0355wxr.com';
	$return = str_replace('{host}', $host, $returnvalue);
	return $return;
}


function dstrlen($str) {
	if(strtolower(CHARSET) != 'utf-8') {
		return strlen($str);
	}
	$count = 0;
	for($i = 0; $i < strlen($str); $i++){
		$value = ord($str[$i]);
		if($value > 127) {
			$count++;
			if($value >= 192 && $value <= 223) $i++;
			elseif($value >= 224 && $value <= 239) $i = $i + 2;
			elseif($value >= 240 && $value <= 247) $i = $i + 3;
	    	}
    		$count++;
	}
	return $count;
}

function cutstr($string, $length, $dot = ' ...') {
	if(strlen($string) <= $length) {
		return $string;
	}

	$pre = chr(1);
	$end = chr(1);
	$string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array($pre.'&'.$end, $pre.'"'.$end, $pre.'<'.$end, $pre.'>'.$end), $string);

	$strcut = '';
	if(strtolower(CHARSET) == 'utf-8') {

		$n = $tn = $noc = 0;
		while($n < strlen($string)) {

			$t = ord($string[$n]);
			if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
				$tn = 1; $n++; $noc++;
			} elseif(194 <= $t && $t <= 223) {
				$tn = 2; $n += 2; $noc += 2;
			} elseif(224 <= $t && $t <= 239) {
				$tn = 3; $n += 3; $noc += 2;
			} elseif(240 <= $t && $t <= 247) {
				$tn = 4; $n += 4; $noc += 2;
			} elseif(248 <= $t && $t <= 251) {
				$tn = 5; $n += 5; $noc += 2;
			} elseif($t == 252 || $t == 253) {
				$tn = 6; $n += 6; $noc += 2;
			} else {
				$n++;
			}

			if($noc >= $length) {
				break;
			}

		}
		if($noc > $length) {
			$n -= $tn;
		}

		$strcut = substr($string, 0, $n);

	} else {
		$_length = $length - 1;
		for($i = 0; $i < $length; $i++) {
			if(ord($string[$i]) <= 127) {
				$strcut .= $string[$i];
			} else if($i < $_length) {
				$strcut .= $string[$i].$string[++$i];
			}
		}
	}

	$strcut = str_replace(array($pre.'&'.$end, $pre.'"'.$end, $pre.'<'.$end, $pre.'>'.$end), array('&amp;', '&quot;', '&lt;', '&gt;'), $strcut);

	$pos = strrpos($strcut, chr(1));
	if($pos !== false) {
		$strcut = substr($strcut,0,$pos);
	}
	return $strcut.$dot;
}

function dstripslashes($string) {
	if(empty($string)) return $string;
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = dstripslashes($val);
		}
	} else {
		$string = stripslashes($string);
	}
	return $string;
}





function debug($var = null, $vardump = false) {
	echo '<pre>';
	$vardump = empty($var) ? true : $vardump;
	if($vardump) {
		var_dump($var);
	} else {
		print_r($var);
	}
	exit();
}

function debuginfo() {
	global $_G;
	if(getglobal('setting/debug')) {
		$db = & DB::object();
		$_G['debuginfo'] = array(
		    'time' => number_format((microtime(true) - $_G['starttime']), 6),
		    'queries' => $db->querynum,
		    'memory' => ucwords(C::memory()->type)
		    );
		if($db->slaveid) {
			$_G['debuginfo']['queries'] = 'Total '.$db->querynum.', Slave '.$db->slavequery;
		}
		return TRUE;
	} else {
		return FALSE;
	}
}

function showmessage($message, $url_forward = '', $values = array(), $extraparam = array(), $custom = 0) {
	require_once libfile('function/message');
	return dshowmessage($message, $url_forward, $values, $extraparam, $custom);
}

function submitcheck($var, $allowget = 0, $seccodecheck = 0, $secqaacheck = 0) {
	if(!getgpc($var)) {
		return FALSE;
	} else {
		return helper_form::submitcheck($var, $allowget, $seccodecheck, $secqaacheck);
	}
}

function multi($num, $perpage, $curpage, $mpurl, $maxpages = 0, $page = 10, $autogoto = FALSE, $simple = FALSE, $jsfunc = FALSE) {
	return $num > $perpage ? helper_page::multi($num, $perpage, $curpage, $mpurl, $maxpages, $page, $autogoto, $simple, $jsfunc) : '';
}

function simplepage($num, $perpage, $curpage, $mpurl) {
	return helper_page::simplepage($num, $perpage, $curpage, $mpurl);
}

function censor($message, $modword = NULL, $return = FALSE) {
	return helper_form::censor($message, $modword, $return);
}

function censormod($message) {
	return getglobal('group/ignorecensor') || !$message ? false :helper_form::censormod($message);
}


function runlog($file, $message, $halt=0) {
	helper_log::runlog($file, $message, $halt);
}

function stripsearchkey($string) {
	$string = trim($string);
	$string = str_replace('*', '%', addcslashes($string, '%_'));
	return $string;
}

function dmkdir($dir, $mode = 0777, $makeindex = TRUE){
	if(!is_dir($dir)) {
		dmkdir(dirname($dir), $mode, $makeindex);
		@mkdir($dir, $mode);
		if(!empty($makeindex)) {
			@touch($dir.'/index.html'); @chmod($dir.'/index.html', 0777);
		}
	}
	return true;
}

function dreferer($default = '') {
	global $_G;

	$default = empty($default) && $_ENV['curapp'] ? $_ENV['curapp'].'.php' : '';
	$_G['referer'] = !empty($_GET['referer']) ? $_GET['referer'] : $_SERVER['HTTP_REFERER'];
	$_G['referer'] = substr($_G['referer'], -1) == '?' ? substr($_G['referer'], 0, -1) : $_G['referer'];

	if(strpos($_G['referer'], 'member.php?mod=logging')) {
		$_G['referer'] = $default;
	}

	$reurl = parse_url($_G['referer']);
	if(!empty($reurl['host']) && !in_array($reurl['host'], array($_SERVER['HTTP_HOST'], 'www.'.$_SERVER['HTTP_HOST'])) && !in_array($_SERVER['HTTP_HOST'], array($reurl['host'], 'www.'.$reurl['host']))) {
		if(!in_array($reurl['host'], $_G['setting']['domain']['app']) && !isset($_G['setting']['domain']['list'][$reurl['host']])) {
			$domainroot = substr($reurl['host'], strpos($reurl['host'], '.')+1);
			if(empty($_G['setting']['domain']['root']) || (is_array($_G['setting']['domain']['root']) && !in_array($domainroot, $_G['setting']['domain']['root']))) {
				$_G['referer'] = $_G['setting']['domain']['defaultindex'] ? $_G['setting']['domain']['defaultindex'] : 'index.php';
			}
		}
	} elseif(empty($reurl['host'])) {
		$_G['referer'] = $_G['siteurl'].'./'.$_G['referer'];
	}

	$_G['referer'] = durlencode($_G['referer']);
	return$_G['referer'];
}

function ftpcmd($cmd, $arg1 = '') {
	static $ftp;
	$ftpon = getglobal('setting/ftp/on');
	if(!$ftpon) {
		return $cmd == 'error' ? -101 : 0;
	} elseif($ftp == null) {
		$ftp = & discuz_ftp::instance();
	}
	if(!$ftp->enabled) {
		return $ftp->error();
	} elseif($ftp->enabled && !$ftp->connectid) {
		$ftp->connect();
	}
	switch ($cmd) {
		case 'upload' : return $ftp->upload(getglobal('setting/attachdir').'/'.$arg1, $arg1); break;
		case 'delete' : return $ftp->ftp_delete($arg1); break;
		case 'close'  : return $ftp->ftp_close(); break;
		case 'error'  : return $ftp->error(); break;
		case 'object' : return $ftp; break;
		default       : return false;
	}

}

function diconv($str, $in_charset, $out_charset = CHARSET, $ForceTable = FALSE) {
	global $_G;

	$in_charset = strtoupper($in_charset);
	$out_charset = strtoupper($out_charset);

	if(empty($str) || $in_charset == $out_charset) {
		return $str;
	}

	$out = '';

	if(!$ForceTable) {
		if(function_exists('iconv')) {
			$out = iconv($in_charset, $out_charset.'//IGNORE', $str);
		} elseif(function_exists('mb_convert_encoding')) {
			$out = mb_convert_encoding($str, $out_charset, $in_charset);
		}
	}

	if($out == '') {
		$chinese = new Chinese($in_charset, $out_charset, true);
		$out = $chinese->Convert($str);
	}

	return $out;
}

function renum($array) {
	$newnums = $nums = array();
	foreach ($array as $id => $num) {
		$newnums[$num][] = $id;
		$nums[$num] = $num;
	}
	return array($nums, $newnums);
}

function sizecount($size) {
	if($size >= 1073741824) {
		$size = round($size / 1073741824 * 100) / 100 . ' GB';
	} elseif($size >= 1048576) {
		$size = round($size / 1048576 * 100) / 100 . ' MB';
	} elseif($size >= 1024) {
		$size = round($size / 1024 * 100) / 100 . ' KB';
	} else {
		$size = intval($size) . ' Bytes';
	}
	return $size;
}

function swapclass($class1, $class2 = '') {
	static $swapc = null;
	$swapc = isset($swapc) && $swapc != $class1 ? $class1 : $class2;
	return $swapc;
}

function writelog($file, $log) {
	helper_log::writelog($file, $log);
}


function notification_add($touid, $type, $note, $notevars = array(), $system = 0) {
	return helper_notification::notification_add($touid, $type, $note, $notevars, $system);
}

function manage_addnotify($type, $from_num = 0, $langvar = array()) {
	helper_notification::manage_addnotify($type, $from_num, $langvar);
}

function sendpm($toid, $subject, $message, $fromid = '', $replypmid = 0, $isusername = 0, $type = 0) {
	return helper_pm::sendpm($toid, $subject, $message, $fromid, $replypmid, $isusername, $type);
}

function g_icon($groupid, $return = 0) {
	global $_G;
	if(empty($_G['cache']['usergroups'][$groupid]['icon'])) {
		$s =  '';
	} else {
		if(substr($_G['cache']['usergroups'][$groupid]['icon'], 0, 5) == 'http:') {
			$s = '<img src="'.$_G['cache']['usergroups'][$groupid]['icon'].'" alt="" class="vm" />';
		} else {
			$s = '<img src="'.$_G['setting']['attachurl'].'common/'.$_G['cache']['usergroups'][$groupid]['icon'].'" alt="" class="vm" />';
		}
	}
	if($return) {
		return $s;
	} else {
		echo $s;
	}
}

function memory($cmd, $key='', $value='', $ttl = 0, $prefix = '') {
	if($cmd == 'check') {
		return  C::memory()->enable ? C::memory()->type : '';
	} elseif(C::memory()->enable && in_array($cmd, array('set', 'get', 'rm', 'inc', 'dec'))) {
		if(defined('DEBUG') && DEBUG) {
			if(is_array($key)) {
				foreach($key as $k) {
					C::memory()->debug[$cmd][] = ($cmd == 'get' || $cmd == 'rm' ? $value : '').$prefix.$k;
				}
			} else {
				C::memory()->debug[$cmd][] = ($cmd == 'get' || $cmd == 'rm' ? $value : '').$prefix.$key;
			}
		}
		switch ($cmd) {
			case 'set': return C::memory()->set($key, $value, $ttl, $prefix); break;
			case 'get': return C::memory()->get($key, $value); break;
			case 'rm': return C::memory()->rm($key, $value); break;
			case 'inc': return C::memory()->inc($key, $value ? $value : 1); break;
			case 'dec': return C::memory()->dec($key, $value ? $value : -1); break;
		}
	}
	return null;
}

function ipaccess($ip, $accesslist) {
	return preg_match("/^(".str_replace(array("\r\n", ' '), array('|', ''), preg_quote($accesslist, '/')).")/", $ip);
}

function getcount($tablename, $condition) {
	if(empty($condition)) {
		$where = '1';
	} elseif(is_array($condition)) {
		$where = DB::implode_field_value($condition, ' AND ');
	} else {
		$where = $condition;
	}
	$ret = intval(DB::result_first("SELECT COUNT(*) AS num FROM ".DB::table($tablename)." WHERE $where"));
	return $ret;
}

function sysmessage($message) {
	helper_sysmessage::show($message);
}


function getexpiration() {
	global $_G;
	$date = getdate($_G['timestamp']);
	return mktime(0, 0, 0, $date['mon'], $date['mday'], $date['year']) + 86400;
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val{strlen($val)-1});
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}


function dintval($int, $allowarray = false) {
	$ret = intval($int);
	if($int == $ret || !$allowarray && is_array($int)) return $ret;
	if($allowarray && is_array($int)) {
		foreach($int as &$v) {
			$v = dintval($v, true);
		}
		return $int;
	} elseif($int <= 0xffffffff) {
		$l = strlen($int);
		$m = substr($int, 0, 1) == '-' ? 1 : 0;
		if(($l - $m) === strspn($int,'0987654321', $m)) {
			return $int;
		}
	}
	return $ret;
}

function strhash($string, $operation = 'DECODE', $key = '') {
	$key = md5($key != '' ? $key : getglobal('authkey'));
	if($operation == 'DECODE') {
		$hashcode = gzuncompress(base64_decode(($string)));
		$string = substr($hashcode, 0, -16);
		$hash = substr($hashcode, -16);
		unset($hashcode);
	}
	
	$vkey = substr(md5($string.substr($key, 0, 16)), 4, 8).substr(md5($string.substr($key, 16, 16)), 18, 8);

	if($operation == 'DECODE') {
		return $hash == $vkey ? $string : '';
	}

	return base64_encode(gzcompress($string.$vkey));
}

function inittoken($uuid){
	$token=substr($_G['timestamp'], 0, -7).'\iF'.$uuid.'\iF'.$_G['clientip'];
	$token=strhash($token,'ENCODE');
	return $token;
}

function checkhash($token){
	$deauth=explode("\iF", strhash($token,'DECODE'));
	$autharr=array();
	if(substr($_G['timestamp'], 0, -7)>$deauth[0]){
		$autharr=array(
			'status'=>0,
			'msg'=>'密钥已过期'
		);
	}elseif($deauth[2]!=$_G['clientip']){
		$autharr=array(
			'status'=>0,
			'msg'=>'IP已变更'
		);
	}else{
		$autharr=array(
			'status'=>1,
			'msg'=>$deauth[1]
		);
	}
	return $autharr;
}


function dunserialize($data) {
	if(($ret = unserialize($data)) === false) {
		$ret = unserialize(stripslashes($data));
	}
	return $ret;
}

function browserversion($type) {
	static $return = array();
	static $types = array('ie' => 'msie', 'firefox' => '', 'chrome' => '', 'opera' => '', 'safari' => '', 'mozilla' => '', 'webkit' => '', 'maxthon' => '', 'qq' => 'qqbrowser');
	if(!$return) {
		$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
		$other = 1;
		foreach($types as $i => $v) {
			$v = $v ? $v : $i;
			if(strpos($useragent, $v) !== false) {
				preg_match('/'.$v.'(\/|\s)([\d\.]+)/i', $useragent, $matches);
				$ver = $matches[2];
				$other = $ver !== 0 && $v != 'mozilla' ? 0 : $other;
			} else {
				$ver = 0;
			}
			$return[$i] = $ver;
		}
		$return['other'] = $other;
	}
	return $return[$type];
}

function currentlang() {
	$charset = strtoupper(CHARSET);
	if($charset == 'GBK') {
		return 'SC_GBK';
	} elseif($charset == 'BIG5') {
		return 'TC_BIG5';
	} elseif($charset == 'UTF-8') {
		global $_G;
		if($_G['config']['output']['language'] == 'zh_cn') {
			return 'SC_UTF8';
		} elseif ($_G['config']['output']['language'] == 'zh_tw') {
			return 'TC_UTF8';
		}
	} else {
		return '';
	}
}


/**
* url 为服务的url地址
* query 为请求串
*/
function sock_post($url,$query){
	$data = "";
	$info=parse_url($url);
	$fp=fsockopen($info["host"],80,$errno,$errstr,30);
	if(!$fp){
		return $data;
	}
	$head="POST ".$info['path']." HTTP/1.0\r\n";
	$head.="Host: ".$info['host']."\r\n";
	$head.="Referer: http://".$info['host'].$info['path']."\r\n";
	$head.="Content-type: application/x-www-form-urlencoded\r\n";
	$head.="Content-Length: ".strlen(trim($query))."\r\n";
	$head.="\r\n";
	$head.=trim($query);
	$write=fputs($fp,$head);
	$header = "";
	while ($str = trim(fgets($fp,4096))) {
		$header.=$str;
	}
	while (!feof($fp)) {
		$data .= fgets($fp,4096);
	}
	return $data;
}

/**
* 模板接口发短信
* apikey 为云片分配的apikey
* tpl_id 为模板id
* tpl_value 为模板值
* mobile 为接受短信的手机号
*/
function tpl_send_sms($apikey, $tpl_id, $tpl_value, $mobile){
	$url="http://yunpian.com/v1/sms/tpl_send.json";
	$encoded_tpl_value = urlencode("$tpl_value");
	$post_string="apikey=$apikey&tpl_id=$tpl_id&tpl_value=$encoded_tpl_value&mobile=$mobile";
	return sock_post($url, $post_string);
}

/**
* 普通接口发短信
* apikey 为云片分配的apikey
* text 为短信内容
* mobile 为接受短信的手机号
*/
function send_sms($apikey, $text, $mobile){
	$url="http://yunpian.com/v1/sms/send.json";
	$encoded_text = urlencode("$text");
	$post_string="apikey=$apikey&text=$encoded_text&mobile=$mobile";
	return sock_post($url, $post_string);
}

//同步图片函数0同步分发1异步分发
function file_sync($cid, $sourceFile, $targetFile, $flag = 1) {

    if (!isset($cid)) {
        return "通道ID不能为空";
    }

    if (!isset($targetFile)) {
        return "目标url不能为空";
    }

    if (!is_file($sourceFile)) {
        return $sourceFile . "文件不存在";
    }

    $url = 'http://fsync2.ifeng.com/sync/sync.do';
    $post = array('file' => '@' . $sourceFile, 'fe.cid' => $cid, 'fe.filepath' =>$targetFile, 'fe.syncflag' => $flag);
   //$post = array('file' => new CURLFile($sourceFile), 'fe.cid' => $cid, 'fe.filepath' =>$targetFile, 'fe.syncflag' => $flag);
    $ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 1000);
    $result = curl_exec($ch);
	curl_close($ch);
    $msg = '';
    if ($result) {
        $xml = simplexml_load_string($result);
        if ($xml->ret == 1) {
            $msg = '文件上传失败';
        } elseif ($xml->ret == 2) {
            $msg = '文件分发失败';
        } elseif ($xml->ret === 0) {
            $msg = '';
        }
    } else {
        $msg = '调用分发接口失败';
    }
    return $msg;
}                   
/**
 * 生成图片缩略图
 *
 * @param   string  $src    原图地址
 * @param   string  $savePath 缩略图保存地址
 * @param   integer $width  缩略图宽
 * @param   integer $height 缩略图高
 * @return  string  缩略图地址
 */
 function buildThumb($src, $savePath, $width = 140, $height = 140)
 {
     $arr = getimagesize($src);
     if (!is_array($arr)) {
         return false;
     }
     //1,2,3 分别为gif,jpg,png
     if ($arr[2] > 4) {
         return false;
     }
	 //计算缩略图宽度、高度，等比缩放
	 if ($width && ($arr[0] < $arr[1])) {//缩放算法
    	 $width = ($height/$arr[1]) * $arr[0];
	 }else
	 {
	  	$height = ($width/$arr[0])*$arr[1];
	 }
     $func = imagecreatefrom;
     switch ($arr[2]) {
         case 1  : $func .= gif; break;
         case 2  : $func .= jpeg; break;
         case 3  : $func .= png; break;
         default :  $func .= jpeg;
     }
     $srcIm = $func($src);
     $im    = imagecreatetruecolor($width, $height);
     imagecopyresized($im, $srcIm, 0, 0, 0, 0, $width, $height, $arr[0], $arr[1]);
     imagejpeg($im, $savePath);
     imagedestroy($srcIm);
     imagedestroy($im);
     return true;
 }
 
////////////////////////////////////图片水印函数开始

function imageWaterMark($groundImage,$waterPos,$waterImage,$waterText="",$fontSize=14,$textColor="#CCCCCC", $fontfile='./arial.ttf',$xOffset=0,$yOffset=0)

{

   $isWaterImage = FALSE;

     //读取水印文件

     if(!empty($waterImage) && file_exists($waterImage)) {

         $isWaterImage = TRUE;

         $water_info = getimagesize($waterImage);

         $water_w     = $water_info[0];//取得水印图片的宽

         $water_h     = $water_info[1];//取得水印图片的高



         switch($water_info[2])   {    //取得水印图片的格式  

             case 1:$water_im = imagecreatefromgif($waterImage);break;

             case 2:$water_im = imagecreatefromjpeg($waterImage);break;

             case 3:$water_im = imagecreatefrompng($waterImage);break;

             default:return 1;

         }

     }



     //读取背景图片

     if(!empty($groundImage) && file_exists($groundImage)) {

         $ground_info = getimagesize($groundImage);

         $ground_w     = $ground_info[0];//取得背景图片的宽

         $ground_h     = $ground_info[1];//取得背景图片的高



         switch($ground_info[2]) {    //取得背景图片的格式  

             case 1:$ground_im = imagecreatefromgif($groundImage);break;

             case 2:$ground_im = imagecreatefromjpeg($groundImage);break;

             case 3:$ground_im = imagecreatefrompng($groundImage);break;

             default:return 1;

         }

     } else {

         return 2;

     }



     //水印位置

     if($isWaterImage) { //图片水印  

         $w = $water_w;

         $h = $water_h;

         $label = "图片的";

         } else {  

     //文字水印

        if(!file_exists($fontfile))return 4;

         $temp = imagettfbbox($fontSize,0,$fontfile,$waterText);//取得使用 TrueType 字体的文本的范围

         $w = $temp[2] - $temp[6];

         $h = $temp[3] - $temp[7];

         unset($temp);

     }

     if( ($ground_w < $w) || ($ground_h < $h) ) {

         return 3;

     }

     switch($waterPos) {

         case 0://随机

             $posX = rand(0,($ground_w - $w));

             $posY = rand(0,($ground_h - $h));

             break;

         case 1://1为顶端居左

             $posX = 0;

             $posY = -5;

             break;

         case 2://2为顶端居中

             $posX = ($ground_w - $w) / 2;

             $posY = 0;

             break;

         case 3://3为顶端居右

             $posX = $ground_w - $w;

             $posY = 0;

             break;

         case 4://4为中部居左

             $posX = 0;

             $posY = ($ground_h - $h) / 2;

             break;

         case 5://5为中部居中

             $posX = ($ground_w - $w) / 2;

             $posY = ($ground_h - $h) / 2;

             break;

         case 6://6为中部居右

             $posX = $ground_w - $w;

             $posY = ($ground_h - $h) / 2;

             break;

         case 7://7为底端居左

             $posX = 0;

             $posY = $ground_h - $h;

             break;

         case 8://8为底端居中

             $posX = ($ground_w - $w) / 2;

             $posY = $ground_h - $h;

             break;

         case 9://9为底端居右

             $posX = $ground_w - $w;

             $posY = $ground_h - $h;

             break;

         default://随机

             $posX = rand(0,($ground_w - $w));

             $posY = rand(0,($ground_h - $h));

             break;     

     }



     //设定图像的混色模式

     imagealphablending($ground_im, true);



     if($isWaterImage) { //图片水印

         imagecopy($ground_im, $water_im, $posX + $xOffset, $posY + $yOffset, 0, 0, $water_w,$water_h);//拷贝水印到目标文件         

     } else {//文字水印

         if( !empty($textColor) && (strlen($textColor)==7) ) {

             $R = hexdec(substr($textColor,1,2));

             $G = hexdec(substr($textColor,3,2));

             $B = hexdec(substr($textColor,5));

         } else {

           return 5;

         }

         imagettftext ( $ground_im, $fontSize, 0, $posX + $xOffset, $posY + $h + $yOffset, imagecolorallocate($ground_im, $R, $G, $B), $fontfile, $waterText);

     }



     //生成水印后的图片

     @unlink($groundImage);

     switch($ground_info[2]) {//取得背景图片的格式

         case 1:imagegif($ground_im,$groundImage);break;

         case 2:imagejpeg($ground_im,$groundImage);break;

         case 3:imagepng($ground_im,$groundImage);break;

         default: return 6;

     }



     //释放内存

     if(isset($water_info)) unset($water_info);

     if(isset($water_im)) imagedestroy($water_im);

     unset($ground_info);

     imagedestroy($ground_im);

}

///////////////////////////////////图片水印函数结束
?>