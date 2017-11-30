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
			$session = strlen($_COOKIE['session'])===40
				? self::$db->getRow(self::$db->prepare("SELECT * FROM sessions WHERE sid=@VAL LIMIT 1", $_COOKIE['session']))
				: false;
			if($session) {
				$this->sid = $session['sid'];
				$valid = true;
				self::$db->query(self::$db->prepare("UPDATE sessions SET t=NOW(), ip=@VAL WHERE sid=@VAL LIMIT 1", $ip, $this->sid));
				$multipart_fields = [];
				foreach(self::$db->query(self::$db->prepare("SELECT * FROM sessionstore WHERE sid=@VAL", $this->sid)) as $row) {
					$multipart = strstr($row['key'], '#');
					if($multipart) {
						$part = (int) substr($multipart, 2);
						$fieldname = substr($row['key'], 0, -strlen($multipart));
						if(!isset($multipart_fields[$fieldname])) {
							$multipart_fields[$fieldname] = [];
						}
						$multipart_fields[$fieldname][$part] = $row['value'];
					}else{
						$this->store[$row['key']] = $row['value'];
					}
				}
				if($multipart_fields) {
					foreach($multipart_fields as $fieldname=>$parts) {
						$value = '';
						for($i=0; $i<count($parts); $i++) {
							if(!isset($parts[$i])) continue;
							$value.= $parts[$i];
						}
						$this->store[$fieldname] = $value;
					}
				}
			}
		}
		if(!$valid || (Config::get('session', 'browser_fingerprint') && $this->get('browser-fingerprint') && $this->get('browser-fingerprint')!==$this->getBrowserFingerprint())) {
			$this->sid = self::generateSid();
			self::$db->query(self::$db->prepare("INSERT INTO sessions SET sid=@VAL, ip=@VAL, t=NOW()", $this->sid, $ip));
			if(Config::get('session', 'browser_fingerprint')) {
				$this->set('browser-fingerprint', self::getBrowserFingerprint());
			}
		}
		setcookie('session', $this->sid, time()+86400*Config::get('session','lifetime_days'), '/', Config::get('session','cookiedomain'));
	}

	/** @internal */
	protected static function generateSid() {
		$sid = hash('sha256', Random::generateBytes(64).uniqid('',true).Config::get('session', 'salt'), true);
		$sid = strtr(base64_encode($sid), '=/+', '---');
		$sid = substr($sid.str_repeat('-', 40), 0, 40);
		return $sid;
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
	 * If not set, `null` is returned.
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
	 * Returns the value for the `$key` in the `Session` and immediately removes it.
	 *
	 * If not set, `null` is returned.
	 * 
	 * @access public
	 * @param string $key
	 * @return mixed
	 */
	public function pop($key) {
		$val = $this->get($key);
		if($val!==null) {
			$this->remove($key);
		}
		return $val;
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
				$dbvalue = $this->store[$key];
				self::$db->query(self::$db->prepare("DELETE FROM sessionstore WHERE sid=@VAL AND (`key`=@VAL OR `key` LIKE @VAL)", $this->sid, $dbkey, $dbkey.'#%'));
				if($dbvalue!==null) {
					if(strlen($dbvalue)>255) {
						$sql = '';
						$chunks = str_split($dbvalue, 255);
						for($i=0; $i<count($chunks); $i++) {
							$sql.= self::$db->prepare("INSERT INTO sessionstore SET sid=@VAL, `key`=@VAL, `value`=@VAL", $this->sid, $dbkey.'#'.$i, $chunks[$i]).';';
						}
						self::$db->multiQuery($sql);
					}else{
						self::$db->query(self::$db->prepare("INSERT INTO sessionstore SET sid=@VAL, `key`=@VAL, `value`=@VAL", $this->sid, $dbkey, $dbvalue));
					}
				}
			}
		}
	}

	/**
	 * Calculates a fingerprint hash (32-bit hex value) based on browser information.
	 *
	 * @access public
	 * @return string
	 */
	public function getBrowserFingerprint() {
		$entropy = '';
		$entropy.= $_SERVER['HTTP_HOST']."\n";
		$entropy.= $_SERVER['HTTP_USER_AGENT']."\n";
		$entropy.= $_SERVER['HTTP_ACCEPT']."\n";
		$entropy.= $_SERVER['HTTP_ACCEPT_ENCODING']."\n";
		$entropy.= $_SERVER['HTTP_ACCEPT_LANGUAGE']."\n";
		return md5($entropy);
	}

}
