<?php

/**
 * The website's `User` entity.
 */
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

	/**
	 * Checks if the user is active.
	 * 
	 * @access public
	 * @return bool `true` if active, `false` if inactive.
	 */
	public function isActive() {
		return (bool) $this->getField('active');
	}

	/**
	 * Activates the user.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function activate() {
		if($this->getField('mailing')) {
			NewsletterSubscriber::addCustomer($this->getField('email'));
		}
		$expires = date('Y-m-d', strtotime('+1 year'));
		return self::$db->query(self::$db->prepare("UPDATE `users` SET `active`=1, `expires`=@VAL WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
	}

	/**
	 * Deactivates the user.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function deactivate() {
		return self::$db->query(self::$db->prepare("UPDATE `users` SET `active`=0 WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
	}

	/**
	 * Authenticates a user.
	 *
	 * If the authentication is successful, the user ID is returned.
	 * On failed authentication attemts, `null` is returned.
	 * If the authentication fails because the user is inactive, `false` is returned.
	 * 
	 * @access public
	 * @static
	 * @param mixed $email The user's email address
	 * @param mixed $passhash The user's hashed password
	 * @return mixed
	 */
	public static function authenticate($email, $passhash) {
		$user = self::db()->getRow(self::db()->prepare("SELECT uid, active FROM users WHERE email=@VAL AND passhash=@VAL LIMIT 1", $email, $passhash));
		if(!$user) return null;
		if(!$user['active']) return false;
		return $user['uid'];
	}

	/**
	 * Checks if a user ID exists.
	 * 
	 * @access public
	 * @static
	 * @param string $uid
	 * @return mixed Returns `false` if the user ID does not exist and the actual user ID if it does
	 */
	public static function checkUid($uid) {
		if(!$uid) return false;
		$uid = self::db()->single(self::db()->prepare("SELECT uid FROM users WHERE uid=@VAL LIMIT 1", $uid));
		return $uid ? $uid : false;
	}

	/**
	 * See if the user account is expired.
	 * 
	 * @access public
	 * @return bool `true` if expired, `false` otherwise
	 */
	public function isExpired() {
		$expires = $this->getField('expires');
		if(!$expires) return false;
		$today = strtotime(date('Y-m-d'));
		$expires = strtotime($expires);
		return $today>=$expires;
	}

	/**
	 * Extends the date of expiry of the user account.
	 * 
	 * @access public
	 * @param string $extension (optional) Amount of expiry extension (default: '+1 year')
	 * @return bool `true` on success, `false` on error
	 */
	public function extendExpiry($extension='+1 year') {
		$expires = date('Y-m-d', strtotime($this->getField('expires').' '.$extension));
		return self::$db->query(self::$db->prepare("UPDATE `users` SET `expires`=@VAL WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
	}

	/**
	 * Get the user ID stored in the `Session`.
	 *
	 * If no `Session` is present or it contains no user ID, `null` is returned.
	 * This method can be used to check if a user is currently logged in.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @see Session
	 */
	public static function getSessionUid() {
		$session = App::getSession();
		if(!$session) return null;
		return $session->get('uid');
	}

	/**
	 * Returns the `User` object of the user with the given email address.
	 *
	 * If no user with this email address exists, `null` is returned.
	 * 
	 * @access public
	 * @static
	 * @param string $email
	 * @return User
	 */
	public static function getUserByEMail($email) {
		if(!$email) return null;
		$uid = self::db()->single(self::db()->prepare("SELECT `uid` FROM `users` WHERE `email`=@VAL LIMIT 1", $email));
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
