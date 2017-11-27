<?php

/**
 * Password Hashing and Checking.
 *
 * Using optional external hashing service.
 */
class Password extends ISystem {

	protected $password_sha256 = '';

	/**
	 * Triggers a notification.
	 * 
	 * @access public
	 * @param string $password
	 * @param bool $is_prehashed (optional)
	 * @return void
	 */
	public function __construct($password, $is_prehashed=false) {
		if($is_prehashed && preg_match('/^[0-9a-f]{64}$/', $password)) {
			$this->password_sha256 = $password;
		}else{
			$this->password_sha256 = hash('sha256', $password);
		}
	}

	/**
	 * Tries to use external hashing service.
	 * 
	 * @access public
	 * @param string $param
	 * @return mixed `false` if hashing service not present, otherwhise the hashed string
	 */
	protected function tryHashUrl($param) {
		$hash_url = Config::get('env', 'password_hash_url');
		if(!$hash_url) return false;
		return @file_get_contents($hash_url.$param);
	}

	/**
	 * Password generator.
	 * 
	 * @access public
	 * @static
	 * @param int $length (optional, default = 8)
	 * @return string
	 */
	public static function generate($length=8) {
		return Random::generateStringSkippingVovels($length);
	}

	/**
	 * Returns the password hash. (length <= 64)
	 * 
	 * @access public
	 * @return string
	 */
	public function hash() {
		$hash = $this->tryHashUrl($this->password_sha256);
		if(!$hash) $hash = $this->password_sha256;
		return $hash;
	}

	/**
	 * Matches the password against a hash or `User`.
	 * 
	 * @access public
	 * @param mixed $hash_or_user Password hash or `User` object
	 * @return mixed `false` if password is not matching, otherwhise a newly generated password hash (if applicable)
	 */
	public function match($hash_or_user) {
		if($hash_or_user instanceof User) {
			$hash = $hash_or_user->getStr('passhash');
		}else{
			$hash = $hash_or_user;
		}
		if(preg_match('/^[0-9a-f]{64}$/', $hash)) {
			if($hash===$this->password_sha256) {
				return $hash;
			}else{
				return false;
			}
		}
		$match = $this->tryHashUrl($this->password_sha256.'/'.$hash);
		if($match===false) {
			Error::warning('Password hashing service call failed while matching passwords.');
		}
		return $match ?: false;
	}

}
