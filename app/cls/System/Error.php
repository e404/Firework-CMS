<?php

/**
 * Error class.
 */
class Error extends NISystem {

	private static $debug_msg_counter = 0;
	private static $mode = null;
	private static $emptyFieldPlaceholder = '(not set)';
	private static $errors_count = 0;

	/**
	 * Throws an error warning.
	 *
	 * This will not halt the application.
	 * Depending on your settings, the warning might be sent to the administrator's email address.
	 * 
	 * @access public
	 * @static
	 * @param string $msg The actual error message
	 * @return void
	 */
	public static function warning($msg='') {
		trigger_error($msg, E_USER_WARNING);
	}

	/**
	 * Throws a fatal error.
	 *
	 * **This will halt the application**.
	 * Depending on your settings, the error might be sent to the administrator's email address.
	 * 
	 * @access public
	 * @static
	 * @param string $msg The actual error message
	 * @return void
	 */
	public static function fatal($msg='') {
		trigger_error($msg, E_USER_ERROR);
	}

	/** @internal */
	public static function deprecated($use_instead=null) {
		trigger_error('This function is deprecated and will be removed in a future version.'.($use_instead ? ' Use '.$use_instead.' instead.' : ''), E_USER_WARNING);
	}

	/**
	 * Logs a debug message.
	 *
	 * Depending on your settings, the message might be sent to the administrator's email address.
	 * 
	 * @access public
	 * @static
	 * @param string $msg The actual debug message
	 * @return void
	 */
	public static function debug($msg='') {
		trigger_error($msg, E_USER_NOTICE);
	}

	/**
	 * Return a placeholder text for an empty field.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function emptyField() {
		return self::$emptyFieldPlaceholder;
	}

	/**
	 * Sets the placeholder text for an empty field.
	 * 
	 * @access public
	 * @static
	 * @param string $str
	 * @return void
	 */
	public static function setEmptyFieldPlaceholder($str) {
		self::$emptyFieldPlaceholder = $str;
	}

	protected static function debugErrorGetRelativeFilePath($file) {
		$app_dir = App::getAppDir();
		if(substr($file, 0, strlen($app_dir))===$app_dir) {
			return substr($file,strlen($app_dir));
		}
		return $file;
	}

	/**
	 * Sets the error handler mode.
	 * 
	 * @access public
	 * @static
	 * @param string $mode 'debug' or 'production'
	 * @return void
	 */
	public static function setMode($mode) {
		if(self::$mode===$mode) return;
		switch($mode) {
			case 'debug':
				self::$mode = 'debug';
				@ini_set('display_errors',true);
				error_reporting(E_ALL);
				set_error_handler('Error::debugErrorHandler', E_ALL);
				break;
			case 'production':
				self::$mode = 'production';
				@ini_set('display_errors',false);
				error_reporting(E_ALL ^E_NOTICE);
				set_error_handler('Error::productionErrorHandler');
		}
		register_shutdown_function('Error::fatalErrorHandler');
	}

	/**
	 * Returns the error handler mode.
	 * 
	 * @access public
	 * @static
	 * @return string 'debug' or 'production'
	 * @see self::setMode()
	 */
	public static function getMode() {
		return self::$mode;
	}

	/** @internal */
	public static function debugErrorHandlerReturn($errno, $errstr, $errfile, $errline) {
		$html = '';
		$errno = $errno & error_reporting();
		if(!$errno) return;
		if(!defined('E_STRICT')) define('E_STRICT', 2048);
		if(!defined('E_RECOVERABLE_ERROR')) define('E_RECOVERABLE_ERROR', 4096);
		$html.= "<pre class=\"error\" style=\"font-family:monospace\">\n<b>";
		switch($errno){
			case E_ERROR: $html.= "Error"; break;
			case E_WARNING: $html.= "Warning"; break;
			case E_PARSE: $html.= "Parse Error"; break;
			case E_NOTICE: $html.= "Notice"; break;
			case E_CORE_ERROR: $html.= "Core Error"; break;
			case E_CORE_WARNING: $html.= "Core Warning"; break;
			case E_COMPILE_ERROR: $html.= "Compile Error"; break;
			case E_COMPILE_WARNING: $html.= "Compile Warning"; break;
			case E_USER_ERROR: $html.= "User Error"; break;
			case E_USER_WARNING: $html.= "User Warning"; break;
			case E_USER_NOTICE: $html.= "User Notice"; break;
			case E_STRICT: $html.= "Strict Notice"; break;
			case E_RECOVERABLE_ERROR: $html.= "Recoverable Error"; break;
			default: $html.= "Error (#$errno)"; break;
		}
		$html.= ":</b> $errstr\n\n";
		$backtrace = debug_backtrace();
		foreach($backtrace as $l){
			if((isset($l['file']) && $l['file']===__FILE__) || (isset($l['class']) && $l['class']===__CLASS__)) continue;
			$l['file'] = isset($l['file']) ? self::debugErrorGetRelativeFilePath($l['file']) : '(anonymous)';
			if(!isset($l['class'])) $l['class'] = '';
			if(!isset($l['type'])) $l['type'] = '';
			if(!isset($l['function'])) $l['function'] = '(anonymous function)';
			$l['line'] = isset($l['line']) ? " : {$l['line']}" : '';
			$html.= "<div style=\"margin-top:0.5em;\"><b>{$l['class']}{$l['type']}{$l['function']}</b>\n";
			if($l['file']) $html.= "{$l['file']}{$l['line']}\n";
			$html.= "</div>";
		}
		$html.= "</pre>";
		return $html;
	}

	/** @internal */
	public static function debugErrorHandler($errno, $errstr, $errfile, $errline) {
		if($errno & (E_NOTICE | E_USER_NOTICE)) {
			if($errno & E_NOTICE) {
				$file = self::debugErrorGetRelativeFilePath($errfile);
				$err = htmlspecialchars($errstr).'<br>'.htmlspecialchars("$file : $errline");
			}else{
				$err = htmlspecialchars($errstr);
			}
			$err = nl2br($err, false);
			self::$debug_msg_counter++;
			setrawcookie('debug_msg'.self::$debug_msg_counter, rawurlencode($err));
			return;
		}
		$html = self::debugErrorHandlerReturn($errno, $errstr, $errfile, $errline);
		if($html) {
			self::$errors_count++;
			echo $html;
		}
		switch($errno){
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				die();
		}
	}

	/** @internal */
	public static function productionErrorHandler($errno, $errstr, $errfile, $errline) {
		if($errno & (E_NOTICE | E_USER_NOTICE)) {
			return;
		}
		$error = self::debugErrorHandlerReturn($errno, $errstr, $errfile, $errline);
		if(!$error) return;
		self::$errors_count++;
		$email = Config::get('email', 'admin_notify_addr');
		$fatal = false;
		switch($errno){
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
				echo '<div class="error"><p><b>ERROR</b></p><p>A fatal error occured, please accept our apology.<br>The error was automatically reported to our developers and will be fixed soon.</p></div>';
				$fatal = true;
		}
		if(PHP_SAPI!=='cli' && isset($_SERVER['REQUEST_URI'])) {
			$url = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			$session = isset($_COOKIE['session']) ? $_COOKIE['session'] : '-';
		}else{
			$url = '(CLI)';
			$session = '-';
		}
		@mail($email, ($fatal ? 'Fatal Error' : 'Error Warning').' at '.App::getHost(), "<html><head><style>body{font-family:monospace;background:#555;margin:30px;padding:0;} .info,.error{background:#FFE8DF;padding:2em;margin:0 0 30px 0;border-top:2px solid #f00;font-size:16px;} .info{background:#fff;border-top:2px solid #3988FF;}</style></head><body>$error<div class=\"info\"><b>URL</b>: ".htmlspecialchars($url)."<br><br><b>Session</b>: $session<br><br><b>User Agent</b>: ".htmlspecialchars(PHP_SAPI==='cli' ? 'CLI' : $_SERVER['HTTP_USER_AGENT'])."</div></body></html>", implode("\n", array(
			'From: '.$email,
			'Content-Type: text/html; charset=UTF-8',
			'Precedence: bulk'
		)));
		if($fatal) die();
	}

	/** @internal */
	public static function fatalErrorHandler() {
		$error = error_get_last();
		if($error!==null) {
			$errno = $error['type'];
			$errfile = $error['file'];
			$errline = $error['line'];
			$errstr = $error['message'];
			switch(self::$mode) {
				case 'debug':
					self::debugErrorHandler($errno, $errstr, $errfile, $errline);
					break;
				default:
					self::productionErrorHandler($errno, $errstr, $errfile, $errline);
			}
		}
	}

	/**
	 * Get the number of encountered errors so far.
	 * 
	 * @access public
	 * @static
	 * @return int
	 */
	public static function getErrorsCount() {
		return self::$errors_count;
	}

}
