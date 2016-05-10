<?php

class Cache {

	private static $dir = null;
	private static $uri = null;
	private static $last_inline_id = null;
	private static $last_inline_error_count = 0;

	public static function setDirectory($dir) {
		$realdir = @realpath($dir);
		if(!$realdir) return false;
		self::$dir = rtrim(realpath($realdir),"/");
		if(PHP_SAPI!=='cli') {
			self::$uri = rtrim(dirname($_SERVER['REQUEST_URI']).'/'.trim($dir,'/'));
		}
		return true;
	}

	public static function getDirectory() {
		return self::$dir;
	}

	public static function setUri($uri) {
		self::$uri = rtrim($uri);
	}

	public static function getUri() {
		return self::$uri;
	}

	public static function getFilename($cachefile) {
		if(self::$dir===null) return null;
		return self::$dir.'/'.$cachefile;
	}

	public static function exists($cachefile) {
		$cachefile = self::getFilename($cachefile);
		return file_exists($cachefile) ? $cachefile : false;
	}

	public static function getAge($cachefile) {
		if($cachefile = self::exists($cachefile)) {
			return time() - filemtime($cachefile);
		}
		return false;
	}

	public static function isOutdated($cachefile,$filename) {
		if($cachefile = self::exists($cachefile)) {
			$original = @filemtime($filename);
			$cached = @filemtime($cachefile);
			if($original>0 && $cached>$original) return false;
		}
		return true;
	}

	public static function writeFile($cachefile,$data,$phpdata=false) {
		if(!($cachefile = self::getFilename($cachefile))) return false;
		if($phpdata) $data = serialize($data);
		return @file_put_contents($cachefile,$data) ? true : false;
	}

	public static function readFile($cachefile,$phpdata=false) {
		if(!($cachefile = self::getFilename($cachefile))) return null;
		$data = @file_get_contents($cachefile);
		return $phpdata ? unserialize($data) : $data;
	}

	public static function getAutoCachename($filename) {
		return 'auto.'.md5($filename).'.private.phpdata';
	}

	public static function get($filename,$max_age_sec) {
		$cachefile = self::getAutoCachename($filename);
		if(self::exists($cachefile) && self::getAge($cachefile)<$max_age_sec) {
			Error::debug('Read cache: '.$filename);
			return self::readFile($cachefile,true);
		}else{
			return null;
		}
	}

	public static function set($filename,$data) {
		Error::debug('Writing cache: '.$filename);
		$cachefile = self::getAutoCachename($filename);
		return self::writeFile($cachefile,$data,true) ? $data : false;
	}

	public static function code($fn, $max_age_sec) {
		if(!is_callable($fn)) Error::fatal('No callable function found.');
		$bt = debug_backtrace()[0];
		$fn_signature = 'code.'.substr(md5($bt['file'].':'.$bt['line'].':'.filemtime($bt['file'])),5,8);
		$cached = self::get($fn_signature, $max_age_sec);
		if($cached) return $cached;
		$return = $fn();
		self::set($fn_signature, $return);
		return $return;
	}

	public static function inlineOpen($id) {
		if(!$id) {
			Error::fatal('Tried to open cache without specified id.');
			return null;
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

	public static function inlineClose() {
		if(!self::$last_inline_id) {
			Error::fatal('Tried to close unknown inline cache.');
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
