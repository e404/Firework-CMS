<?php

class User extends AbstractDbRecord {

	protected function getTable() {
		return 'users';
	}

	protected function getPrimaryKey() {
		return 'uid';
	}

	protected function generateId() {
		return date('y').mt_rand(1000,9999).mt_rand(1000,9999).mt_rand(1000,9999);
	}

	public function isActive() {
		return (bool) $this->getField('active');
	}

	public function activate() {
		if($this->getField('mailing')) {
			NewsletterSubscriber::addCustomer($this->getField('email'));
		}
		$expires = date('Y-m-d', strtotime('+1 year'));
		return self::$db->query(self::$db->prepare("UPDATE `users` SET `active`=1, `expires`=@VAL WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
	}

	public function deactivate() {
		return self::$db->query(self::$db->prepare("UPDATE `users` SET `active`=0 WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
	}

	public static function authenticate($email, $password) {
		$user = self::db()->getRow(self::db()->prepare("SELECT uid, active FROM users WHERE email=@VAL AND passhash=@VAL LIMIT 1", $email, $password));
		if(!$user) return null;
		if(!$user['active']) return false;
		return $user['uid'];
	}

	public static function checkUid($uid) {
		if(!$uid) return false;
		$uid = self::db()->single(self::db()->prepare("SELECT uid FROM users WHERE uid=@VAL LIMIT 1", $uid));
		return $uid ? $uid : false;
	}

	public function isExpired() {
		$expires = $this->getField('expires');
		if(!$expires) return false;
		$today = strtotime(date('Y-m-d'));
		$expires = strtotime($expires);
		return $today>=$expires;
	}

	public function extendExpiry($extension='+1 year') {
		$expires = date('Y-m-d', strtotime($this->getField('expires').' '.$extension));
		return self::$db->query(self::$db->prepare("UPDATE `users` SET `expires`=@VAL WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
	}

	public static function getSessionUid() {
		$session = App::getSession();
		if(!$session) return null;
		return $session->get('uid');
	}

	public static function getUserByEMail($email) {
		if(!$email) return null;
		$uid = self::db()->single(self::db()->prepare("SELECT `uid` FROM `users` WHERE `email`=@VAL LIMIT 1", $email));
		if(!$uid) return null;
		return self::newInstance($uid);
	}

	public static function getUserByDomain($domain, $where=array()) {
		if(!$domain) return null;
		$sql = self::db()->prepare("SELECT `uid` FROM `users` WHERE `domain`=@VAL", $domain);
		if($where) {
			foreach($where as $field=>$value) {
				$sql.= " AND `".self::db()->escape($field)."`='".self::db()->escape($value)."'";
			}
		}
		$sql.= " LIMIT 1";
		$uid = self::db()->single($sql);
		if(!$uid) return null;
		return self::newInstance($uid);
	}

	/**
	 * Returns the path to user uploaded files meant for permanent storage.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getUserUploadDir() {
		return rtrim(Config::get('dirs', 'user_upload', true),'/').'/';
	}

	/**
	 * Creates a file in the user upload directory and returns its path.
	 * 
	 * @access public
	 * @static
	 * @param string $suffix The suffix of the file.
	 * @return string File path
	 */
	public static function createUploadFile(string $suffix) {
		$path = self::getUploadDir();
		do {
			$subdir = mt_rand(10,99);
			$file = $path.$subdir.'/'.$subdir.mt_rand(10,99).mt_rand(1000,9999).mt_rand(1000,9999).mt_rand(1000,9999).$suffix;
		} while(file_exists($file));
		if(!is_dir($path.$subdir)) {
			mkdir($path.$subdir);
			chmod($path.$subdir, 0777);
		}
		touch($file);
		chmod($file, 0777);
		return $file;
	}

}
