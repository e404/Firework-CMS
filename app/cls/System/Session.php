<?php

/**
 * Session class.
 * 
 * @extends Db
 */
class Session extends Db {

	use Inject;

	protected $sid = null;
	protected $store = array();
	protected $changed = array();

	/**
	 * Returns the client's IP address.
	 *
	 * @access public
	 * @return string
	 */
	public function getRemoteIp() {
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
			$ip = trim(array_pop($ips));
			if($ip) return $ip;
		}
		return $_SERVER['REMOTE_ADDR'];
	}

	/** @internal */
	public function __construct() {
		parent::__construct();
		$ip = $this->getRemoteIp();
		$valid = false;
		if(isset($_COOKIE['session'])) {
			$sid = $_COOKIE['session'];
			$session = strlen($sid)<64
				? self::$db->getRow(self::$db->prepare("SELECT * FROM sessions WHERE sid=@VAL LIMIT 1", $sid))
				: false;
			if($session) {
				$this->sid = $session['sid'];
				$valid = true;
				self::$db->query(self::$db->prepare("UPDATE sessions SET t=NOW(), ip=@VAL WHERE sid=@VAL LIMIT 1", $ip, $this->sid));
				foreach(self::$db->query(self::$db->prepare("SELECT * FROM sessionstore WHERE sid=@VAL", $this->sid)) as $row) {
					$this->store[$row['key']] = $row['value'];
				}
			}
		}
		if(!$valid) {
			$this->sid = sha1(uniqid('',true).Config::get('session', 'salt'));
			self::$db->query(self::$db->prepare("INSERT INTO sessions SET sid=@VAL, ip=@VAL, t=NOW()", $this->sid, $ip));
		}
		setcookie('session',$this->sid,time()+86400*Config::get('session','lifetime_days'),'/',Config::get('session','cookiedomain'));
	}

	/**
	 * Returns the `Session` ID.
	 * 
	 * @access public
	 * @return string
	 */
	public function getSid() {
		return $this->sid;
	}

	/**
	 * Destroys the entire `Session` including all variables.
	 * 
	 * @access public
	 * @return void
	 */
	public function destroy() {
		if(!$this->sid) return;
		setcookie('session','',time()-86400,'/',Config::get('session','cookiedomain'));
		self::$db->query(self::$db->prepare("DELETE FROM sessions WHERE sid=@VAL LIMIT 1", $this->sid));
		$this->sid = null;
		$this->store = array();
		$this->changed = array();
	}

	/**
	 * Saves a value to the `Session`.
	 *
	 * ***Warning:*** The `Session` store saves values to the DB on destruction. This results in late consistency.
	 * **Do not try to query the `sessionstore` table manually!**
	 * 
	 * @access public
	 * @param string $key The internal key to uniquely identify the value
	 * @param mixed $value (optional) Could be a `string` or `array` (default: '1')
	 * @return void
	 */
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

	/**
	 * Returns the value for the `$key` in the `Session`.
	 *
	 * If Not set, `null` is returned.
	 * 
	 * @access public
	 * @param string $key
	 * @return mixed
	 */
	public function get($key) {
		$value = isset($this->store[$key]) ? $this->store[$key] : null;
		if(is_string($value) && strlen($value)>5 && preg_match('/^\{"[^"]+":/',$value)) {
			$obj = @json_decode($value, true);
			if($obj===null) Error::warning('Could not decode JSON array of session key "'.$key.'".');
			else $value = $obj;
		}
		return $value;
	}

	/**
	 * Removes a value from the `Session`.
	 *
	 * If no value with this `$key` has been saved to the `Session` before, nothing will be done and no error will be triggered.
	 * 
	 * @access public
	 * @param string $key
	 * @return void
	 */
	public function remove($key) {
		if(isset($this->store[$key])) {
			$this->store[$key] = null;
			$this->changed[$key] = true;
		}
	}

	/**
	 * Alias for `getSid()`.
	 * 
	 * @access public
	 * @return string
	 * @see self::getSid()
	 */
	public function __toString() {
		return (string) $this->getSid();
	}

	/** @internal */
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
