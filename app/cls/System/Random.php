<?

/**
 * Random generator.
 */
class Random extends NonInstantiable {

	/**
	 * Generates a random string.
	 * 
	 * The string contains upper-case letters, lower-case letters and numbers.
	 *
	 * @access public
	 * @static
	 * @param int $length (optional) The length of the generated random string (default: 6)
	 * @return string
	 * @deprecated Use `Random::generateString()` instead
	 * @see Random::generateString()
	 */
	public static function generate($length=6) {
		Error::deprecated('Random::generateString()');
		return self::generateString($length);
	}

	/**
	 * Generates a random string.
	 * 
	 * The string contains upper-case letters, lower-case letters and numbers.
	 * `[A-Za-z0-9]{$length}`
	 *
	 * @access public
	 * @static
	 * @param int $length The length of the generated random string
	 * @return string
	 */
	public static function generateString($length) {
		// base 62 map
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		// get enough random bits for base 64 encoding (and prevent '=' padding)
		// note: +1 is faster than ceil()
		$bytes = openssl_random_pseudo_bytes(3*$length/4+1);
		// convert base 64 to base 62 by mapping + and / to something from the base 62 map
		// use the first 2 random bytes for the new characters
		$repl = unpack('C2', $bytes);
		$first  = (string) $chars[$repl[1]%62];
		$second = (string) $chars[$repl[2]%62];
		return strtr(substr(base64_encode($bytes), 0, $length), '+/', $first.$second);

	}

	/**
	 * Generates a random string that contains no characters humans often confuse.
	 *
	 * This random generator is intended to be used when humans must type in the code somewhere manually.
	 * 
	 * @access public
	 * @static
	 * @param mixed $length The length of the generated random string
	 * @return string
	 */
	public static function generateEasyCode($length) {
		$code = self::generate($length*5);
		$code = strtoupper($code);
		$code = str_replace(array('A','E','I','1','O','0','U','S','5'), '', $code);
		if(strlen($code)<$length) return self::generateEasyCode($length);
		return substr($code, 0, $length);
	}

}
