<?php

/**
 * The website's `User` entity.
 */
class User extends AbstractDbRecord {

	use Inject;

	const NOT_LOGGED_IN = 'not_logged_in';
	const NON_EXISTENT = 'non_existent';
	const INACTIVE = 'inactive';
	const LOGIN_EXPIRED = 'login_expired';

	protected static $uid_generator = null;

	protected function getTable() {
		return 'users';
	}

	protected function getPrimaryKey() {
		return 'uid';
	}

	/**
	 * Attaches a user-defined ID generator function.
	 * 
	 * @access public
	 * @static
	 * @param callable $function If `null`, auto-increment will be implied.
	 * @return void
	 */
	public static function setUidGenerator($function) {
		self::$uid_generator = $function;
	}

	protected function generateId() {
		if(self::$uid_generator && is_callable(self::$uid_generator)) {
			return call_user_func(self::$uid_generator);
		}else{
			return null;
		}
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
		$success = self::$db->query(self::$db->prepare("UPDATE `users` SET `active`=1 WHERE `uid`=@VAL LIMIT 1", $this->getId()));
		App::executeHooks('user-activated', $this);
		return $success;
	}

	/**
	 * Deactivates the user.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function deactivate() {
		$success = self::$db->query(self::$db->prepare("UPDATE `users` SET `active`=0 WHERE `uid`=@VAL LIMIT 1", $expires, $this->getId()));
		App::executeHooks('user-deactivated', $this);
		return $success;
	}

	/**
	 * Login user.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function login() {
		$session = App::getSession();
		if(!$session) return false;
		$session->set('uid', $this->getId());
		$expires_days = Config::get('session', 'login_valid_days');
		if($expires_days) {
			$session->set('login-expires', time()+86400*$expires_days);
		}
		return true;
	}

	/**
	 * Logout user.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function logout() {
		$session = App::getSession();
		if(!$session || $session->get('uid')!=$this->getId()) return false;
		$session->remove('uid');
		$session->remove('login-expires');
		return true;
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
		$user = self::db()->getRow(self::db()->prepare("SELECT `uid`, `passhash`, `active` FROM `users` WHERE `email`=@VAL LIMIT 1", $email));
		if(!$user) return null;
		$password = new Password($passhash, true);
		if($new_hash = $password->match($user['passhash'])) {
			if($new_hash!==$passhash) {
				self::db()->query(self::db()->prepare("UPDATE `users` SET `passhash`=@VAL WHERE `uid`=@VAL LIMIT 1", $new_hash, $user['uid']));
			}
			if(!$user['active']) return false;
			return $user['uid'];
		}else{
			return null;
		}
	}

	/**
	 * At the beginning of a page, a logged in user can be required.
	 * 
	 * Warning: Halts code execution if no user is logged in after executing `$error_callback` function.
	 * `$error_callback` gets a parameter specifying the reason why it has failed, which is one of the following constants:
	 * `User::NOT_LOGGED_IN`, `User::NON_EXISTENT`, `User::INACTIVE`, `User::LOGIN_EXPIRED`
	 * When `User::INACTIVE` or `User::LOGIN_EXPIRED` is returned, the `User` object is given as a second parameter.
	 * 
	 * @access public
	 * @static
	 * @param callable $error_callback Callback function executed in case no user is logged in
	 * @return User Returns a `User` object in case a user is logged in, otherwhise code execution is halted before anything can be returned
	 */
	public static function requireLogin($error_callback) {
		if(!$error_callback || !is_callable($error_callback)) {
			Error::fatal('Given callback is non-callable in User::requireLogin($error_callback).');
		}
		$session = App::getSession();
		$uid = $session->get('uid');
		if(!$uid) {
			call_user_func($error_callback, self::NOT_LOGGED_IN);
			App::halt();
		}
		$user = new self($uid);
		if(!$user->exists()) {
			call_user_func($error_callback, self::NON_EXISTENT);
			App::halt();
		}
		if(!$user->isActive()) {
			call_user_func($error_callback, self::INACTIVE, $user);
			App::halt();
		}
		$expires = $session->get('login-expires');
		if($expires) {
			if(time()>=$expires) {
				call_user_func($error_callback, self::LOGIN_EXPIRED, $user);
				App::halt();
			}
		}
		if($expires) {
			$expires_days = Config::get('session', 'login_valid_days');
			if($expires_days) {
				$session->set('login-expires', time()+86400*$expires_days);
			}else{
				$session->remove('login-expires');
			}
		}
		return $user;
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
		$expires = $session->get('login-expires');
		if($expires) {
			if(time()>=$expires) {
				return null;
			}
		}
		return $session->get('uid');
	}

	/**
	 * Get the user object by user ID stored in the `Session`.
	 *
	 * If no `Session` is present or it contains no user ID, `null` is returned.
	 * 
	 * @access public
	 * @static
	 * @return User
	 * @see Session
	 */
	public static function getSessionUser() {
		$uid = self::getSessionUid();
		return $uid ? new self($uid) : null;
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
		$email = trim($email);
		if(!$email) return null;
		$uid = self::db()->single(self::db()->prepare("SELECT `uid` FROM `users` WHERE `email` LIKE @VAL LIMIT 1", $email));
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
	public static function getUploadDir() {
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
	public static function createUploadFile($suffix) {
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
