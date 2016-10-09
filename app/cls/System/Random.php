<?

/**
 * Random generator.
 */
class Random extends NISystem {

	/**
	 * Generates a random string.
	 * 
	 * If `$chars` is not set, the string contains upper-case letters, lower-case letters and numbers.
	 * `[0-9a-zA-Z]{$length}`
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
	 * Generates a random string with only consonants and numbers.
	 * 
	 * Can be used to avoid the unintended formation of real words.
	 * The string contains upper-case consonants, lower-case consonants and numbers.
	 * `[0-9b-df-hj-np-tv-zB-DF-HJ-NP-TV-Z]{$length}`
	 *
	 * @access public
	 * @static
	 * @param int $length The length of the generated random string
	 * @return string
	 * @see self::generateString()
	 */
	public static function generateStringSkippingVovels($length) {
		$string = '';
		while(strlen($string)<$length) {
			$string.= str_replace(['a','e','i','o','u','A','E','I','O','U'], '', self::generateString($length));
		}
		return substr($string, 0, $length);
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
		$code = self::generateString($length*5);
		$code = strtoupper($code);
		$code = str_replace(array('A','E','I','1','O','0','U','S','5'), '', $code);
		if(strlen($code)<$length) return self::generateEasyCode($length);
		return substr($code, 0, $length);
	}

}
