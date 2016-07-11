<?php

/**
 * Configuration class.
 */
class Config extends NISystem {

	private static $conf = array();

	/**
	 * Loads a configuration ini file.
	 * 
	 * @access public
	 * @static
	 * @param string $inifile
	 * @return void
	 */
	public static function load($inifile) {
		if(file_exists($inifile) && is_readable($inifile)) {
			if(PHP_SAPI!=='cli') {
				$cachefile = 'config.private.phpdata';
			}else{
				$cachefile = null;
			}
			if($cachefile && Cache::exists($cachefile) && !Cache::isOutdated($cachefile,$inifile)) {
				self::$conf = Cache::readFile($cachefile,true);
			}else{
				self::$conf = @parse_ini_file($inifile,true);
				if($cachefile) {
					Cache::writeFile($cachefile,self::$conf,true);
				}
			}
		}else{
			Error::fatal("Configuration file not found or no read permissions for $inifile");
		}
	}

	/**
	 * Returns a configuration value.
	 * 
	 * @access public
	 * @static
	 * @param string $section
	 * @param string $key (default: null)
	 * @param bool $strict (default: false)
	 * @return mixed
	 */
	public static function get($section,$key=null,$strict=false) {
		if($key===null) $key = $section;
		if(isset(self::$conf[$section]) && isset(self::$conf[$section][$key])) {
			return self::$conf[$section][$key];
		}else{
			if($strict) Error::warning("Config value not set: [$section] $key");
			return null;
		}
	}

	/**
	 * Fakes a config value.
	 *
	 * This method temporarily sets a config value.
	 * Nothing will be saved though.
	 * This is useful for debugging or special cases.
	 * 
	 * @access public
	 * @static
	 * @param string $section
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public static function fake($section, $key, $value) {
		if(!self::$conf) return false;
		if(!isset(self::$conf[$section])) self::$conf[$section] = array();
		self::$conf[$section][$key] = $value;
		return true;
	}

}
