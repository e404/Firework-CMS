<?

class Random extends NonInstantiable {

	public static function generate($length=6) {

		// base 62 map
		$chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		// get enough random bits for base 64 encoding (and prevent '=' padding)
		// note: +1 is faster than ceil()
		$bytes = openssl_random_pseudo_bytes(3*$length/4+1);

		// convert base 64 to base 62 by mapping + and / to something from the base 62 map
		// use the first 2 random bytes for the new characters
		$repl = unpack('C2', $bytes);

		$first  = $chars[$repl[1]%62];
		$second = $chars[$repl[2]%62];

		return strtr(substr(base64_encode($bytes), 0, $length), '+/', "$first$second");

	}

	public static function generateEasyCode($length) {
		$code = self::generate($length*5);
		$code = strtoupper($code);
		$code = str_replace(array('A','E','I','1','O','0','U','S','5'), '', $code);
		if(strlen($code)<$length) return self::generateEasyCode($length);
		return substr($code, 0, $length);
	}

}