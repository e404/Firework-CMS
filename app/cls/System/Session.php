<?php

class Session extends Db {

	protected $sid = null;
	protected $store = array();
	protected $changed = array();

	public function __construct() {
		parent::__construct();
		$valid = false;
		if(isset($_COOKIE['session'])) {
			$sid = $_COOKIE['session'];
			$session = strlen($sid)<64
				? self::$db->getRow(self::$db->prepare("SELECT * FROM sessions WHERE sid=@VAL LIMIT 1", $sid))
				: false;
			if($session) {
				$this->sid = $session['sid'];
				$valid = true;
				self::$db->query(self::$db->prepare("UPDATE sessions SET t=NOW(), ip=@VAL WHERE sid=@VAL LIMIT 1", $_SERVER['REMOTE_ADDR'], $this->sid));
				foreach(self::$db->query(self::$db->prepare("SELECT * FROM sessionstore WHERE sid=@VAL", $this->sid)) as $row) {
					$this->store[$row['key']] = $row['value'];
				}
			}
		}
		if(!$valid) {
			$this->sid = sha1(uniqid('',true).Config::get('session', 'salt'));
			self::$db->query(self::$db->prepare("INSERT INTO sessions SET sid=@VAL, ip=@VAL, t=NOW()", $this->sid, $_SERVER['REMOTE_ADDR']));
		}
		setcookie('session',$this->sid,time()+86400*Config::get('session','lifetime_days'),'/',Config::get('session','cookiedomain'));
	}

	public function getSid() {
		return $this->sid;
	}

	public function destroy() {
		if(!$this->sid) return;
		setcookie('session','',time()-86400,'/',Config::get('session','cookiedomain'));
		self::$db->query(self::$db->prepare("DELETE FROM sessions WHERE sid=@VAL LIMIT 1", $this->sid));
	}

	// Warning: Session store saves to DB on destruction. This results in late consistency.
	// Do not query table "sessionstore" manually!
	public function set($key,$value='1') {
		if(is_array($value)) {
			$value = json_encode($value, JSON_FORCE_OBJECT);
		}else{
			$value = (string) $value;
		}
		if(!isset($this->store[$key]) || $this->store[$key]!==$value) {
			$this->store[$key] = $value;
			$this->changed[$key] = true;
		}
	}

	// Do not query table "sessionstore" manually!
	public function get($key) {
		$value = isset($this->store[$key]) ? $this->store[$key] : null;
		if(is_string($value) && strlen($value)>5 && preg_match('/^\{"[^"]+":/',$value)) {
			$obj = @json_decode($value, true);
			if($obj===null) Error::warning('Could not decode JSON array of session key "'.$key.'".');
			else $value = $obj;
		}
		return $value;
	}

	// Do not query table "sessionstore" manually!
	public function remove($key) {
		if(isset($this->store[$key])) {
			$this->store[$key] = null;
			$this->changed[$key] = true;
		}
	}

	public function __toString() {
		return (string) $this->sid;
	}

	public function __destruct() {
		if($this->changed) {
			foreach($this->changed as $key=>$changed) {
				$dbkey = self::$db->escape($key);
				if($this->store[$key]===null) {
					self::$db->query(self::$db->prepare("DELETE FROM sessionstore WHERE sid=@VAL AND `key`=@VAL", $this->sid, $dbkey));
				}else{
					self::$db->query(self::$db->prepare("REPLACE INTO sessionstore SET sid=@VAL, `key`=@VAL, `value`=@VAL", $this->sid, $dbkey, $this->store[$key]));
				}
			}
		}
	}

}
