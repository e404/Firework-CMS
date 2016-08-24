<?php

/**
 * Automated caching of files and other data.
 */
class Cache extends NISystem {

	private static $dir = null;
	private static $last_inline_id = null;
	private static $last_inline_error_count = 0;

	/**
	 * Sets the cache directory.
	 * 
	 * @access public
	 * @static
	 * @param string $dir
	 * @return bool false on error, true otherwise
	 */
	public static function setDirectory($dir) {
		$realdir = @realpath($dir);
		if(!$realdir) {
			Error::warning('Directory not found: '.$dir);
			return false;
		}
		self::$dir = rtrim(realpath($realdir),"/");
		return true;
	}

	/**
	 * Returns the cache directory.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getDirectory() {
		return self::$dir;
	}

	/**
	 * Returns the path to the cachefile.
	 * 
	 * @access public
	 * @static
	 * @param string $cachefile
	 * @return string
	 */
	public static function getFilename($cachefile) {
		if(self::$dir===null) return null;
		return self::$dir.'/'.$cachefile;
	}

	/**
	 * Check if cachefile exists.
	 * 
	 * @access public
	 * @static
	 * @param string $cachefile
	 * @return string cachefile path if it exists, otherwise false
	 */
	public static function exists($cachefile) {
		$cachefile = self::getFilename($cachefile);
		return file_exists($cachefile) ? $cachefile : false;
	}

	/**
	 * Returns the age in seconds of the cachefile.
	 * 
	 * @access public
	 * @static
	 * @param string $cachefile
	 * @return int file age in seconds, false if it doesn't exist
	 */
	public static function getAge($cachefile) {
		if($cachefile = self::exists($cachefile)) {
			return time() - filemtime($cachefile);
		}
		return false;
	}

	/**
	 * Check if cachefile is outdated.
	 *
	 * Outdated means, the `$cachefile` is older than the original file (`$filename`).
	 * 
	 * @access public
	 * @static
	 * @param string $cachefile
	 * @param string $filename
	 * @return bool
	 */
	public static function isOutdated($cachefile, $filename) {
		if($cachefile = self::exists($cachefile)) {
			$original = @filemtime($filename);
			$cached = @filemtime($cachefile);
			if($original>0 && $cached>$original) return false;
		}
		return true;
	}

	/**
	 * Writes a cachefile.
	 * 
	 * @access public
	 * @static
	 * @param string $cachefile
	 * @param mixed $data
	 * @param bool $is_phpdata (optional) If set to true, `$data` will be serialized (default: false)
	 * @return bool false on error, otherwise true
	 */
	public static function writeFile($cachefile, $data, $is_phpdata=false) {
		if(!($cachefile = self::getFilename($cachefile))) return false;
		if($is_phpdata) $data = serialize($data);
		return @file_put_contents($cachefile,$data) ? true : false;
	}

	/**
	 * Returns the content of the cachefile.
	 * 
	 * @access public
	 * @static
	 * @param string $cachefile
	 * @param bool $is_phpdata (optional) If set to true, the data will be unserialized (default: false)
	 * @return mixed Returns null if `$cachefile` is not available
	 */
	public static function readFile($cachefile, $is_phpdata=false) {
		if(!($cachefile = self::getFilename($cachefile))) return null;
		$data = @file_get_contents($cachefile);
		return $is_phpdata ? unserialize($data) : $data;
	}

	/**
	 * Generates an automated private cachefile name that is virtually unique across the entire application.
	 * 
	 * @access public
	 * @static
	 * @param string $filename
	 * @return string
	 */
	public static function getAutoCachename($filename) {
		return 'auto.'.md5($filename).'.private.phpdata';
	}

	/**
	 * Returns the contents of a cached file.
	 * 
	 * @access public
	 * @static
	 * @param string $filename
	 * @param int $max_age_sec
	 * @return void
	 */
	public static function get($filename, $max_age_sec) {
		$cachefile = self::getAutoCachename($filename);
		if(self::exists($cachefile) && self::getAge($cachefile)<$max_age_sec) {
			Error::debug('Read cache: '.$filename);
			return self::readFile($cachefile,true);
		}else{
			return null;
		}
	}

	/**
	 * Writes cached file contents.
	 * 
	 * @access public
	 * @static
	 * @param string $filename
	 * @param string $data
	 * @return string `$data` on success, false on error
	 */
	public static function set($filename, $data) {
		Error::debug('Writing cache: '.$filename);
		$cachefile = self::getAutoCachename($filename);
		return self::writeFile($cachefile,$data,true) ? $data : false;
	}

	/**
	 * Caches the entire result of executable PHP code.
	 *
	 * The function handles everything, so you only need to write your code and define a maximum age.
	 * 
	 * @access public
	 * @static
	 * @param callable $fn Executable function or method call
	 * @param int $max_age_sec Maximum age in seconds
	 * @return mixed
	 *
	 * @example
	 * <code>
	 * $result = Cache::code(function(){
	 * 	$calculate = rand(); // Big rocket science calculation
	 * 	return $calculate;
	 * }, 600); // Cache the result for 10 minutes
	 * // Use $result like normal
	 * </code>
	 */
	public static function code(callable $fn, $max_age_sec) {
		$bt = debug_backtrace()[0];
		$fn_signature = 'code.'.substr(md5($bt['file'].':'.$bt['line'].':'.filemtime($bt['file'])),5,8);
		$cached = self::get($fn_signature, $max_age_sec);
		if($cached) return $cached;
		$return = $fn();
		self::set($fn_signature, $return);
		return $return;
	}

	/**
	 * Caches an entire part of a document.
	 *
	 * This method should be used within an `if` block.
	 * The result will not be cached if the code encounters an error.
	 *
	 * @access public
	 * @static
	 * @param string $id
	 * @return void
	 *
	 * @example
	 * <code>
	 * <? if(Cache::inlineOpen('my-identifier')): ?>
	 * <h1>Make some <?= 'black' ?> coffee.</h1>
	 * <? Cache::inlineClose(); endif; ?>
	 * </code>
	 */
	public static function inlineOpen($id) {
		if(!$id) {
			return Error::fatal('Tried to open cache without specified id.');
		}
		$cachefile = 'inline-cache-'.$id.'-'.App::getLang().'.private.phpdata';
		self::$last_inline_error_count = Error::getErrorsCount();
		if(self::exists($cachefile)) {
			if(Config::get('debug')) {
				Error::debug('Cached: "'.$id.'"');
			}
			readfile(self::getFilename($cachefile));
			return false;
		}else{
			self::$last_inline_id = $id;
			echo '<inline-cache data-id="'.$id.'">';
			return true;
		}
	}

	/**
	 * Closes a previously opened inline cache.
	 * 
	 * @access public
	 * @static
	 * @return void
	 * @see self::inlineOpen()
	 */
	public static function inlineClose() {
		if(!self::$last_inline_id) {
			Error::fatal('Tried to close inline cache without opening it using Cache::inlineOpen().');
			return;
		}
		echo '</inline-cache>';
		$buffer = ob_get_contents();
		ob_end_clean();
		if(Error::getErrorsCount()>self::$last_inline_error_count) {
			$cachefile = null;
		}else{
			$cachefile = 'inline-cache-'.self::$last_inline_id.'-'.App::getLang().'.private.phpdata';
		}
		$written = false;
		$buffer = preg_replace_callback('/<inline-cache\s+data-id="([^"]+)">(.*?)<\/inline-cache>/s', function($matches) use($cachefile) {
			if($cachefile) self::writeFile($cachefile, $matches[2]);
			return $matches[2];
		}, $buffer);
		ob_start();
		echo $buffer;
		if($cachefile && Config::get('debug')) {
			Error::debug('Inline cache written: "'.self::$last_inline_id.'"');
		}
		self::$last_inline_id = null;
	}

}
