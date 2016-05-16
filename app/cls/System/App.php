<?php

/**
 * ** Main Application. **
 *
 * This main class is not instantiable.
 * However, you can call various static methods.
 *
 * ***TODO:*** (system wide) optimize path handling:
 *
 * - Never end a path with trailing /
 * - Never use hard-coded / as dir separator (use DIRECTORY_SEPARATOR global constant)
 *
 * @copyright Roadfamily LLC, 2016
 * @license ../license.txt
 */
class App {

	/** @internal */
	const PRODUCT = 'Firework CMS';
	/** @internal */
	const VERSION = '1.0.3';

	/** @internal */
	protected static $start_time;
	/** @internal */
	protected static $app_dir = './';
	/** @internal */
	protected static $site_dir = './site/';
	/** @internal */
	protected static $path = null;
	/** @internal */
	protected static $languages = array();
	/** @internal */
	protected static $lang = null;
	/** @internal */
	protected static $session = null;
	/** @internal */
	protected static $query = array();
	/** @internal */
	protected static $title = '';
	/** @internal */
	protected static $title_skip_suffix = false;
	/** @internal */
	protected static $protocol = 'http://';
	/** @internal */
	protected static $host = '';
	/** @internal */
	protected static $uriprefix = '';
	/** @internal */
	protected static $preload = array();
	/** @internal */
	protected static $hooks = array();
	/** @internal */
	protected static $cls_files = array();
	/** @internal */
	protected static $sandboxed = false;
	/** @internal */
	protected static $custom_tags = array();
	/** @internal */
	protected static $js_files = array();

	/**
	 * Sets the application base directory.
	 *
	 * This could be the vhost's root dir.
	 * However, this path is automatically determined by the application.
	 * 
	 * @access public
	 * @static
	 * @param string $dir App directory path
	 * @return void
	 * @see self::getAppDir()
	 */
	public static function setAppDir($dir) {
		self::$app_dir = rtrim($dir,'/').'/';
	}

	/**
	 * Returns the application base directory.
	 *
	 * @access public
	 * @static
	 * @return string
	 * @see self::setAppDir()
	 */
	public static function getAppDir() {
		return self::$app_dir;
	}

	/**
	 * Sets the site directory.
	 *
	 * This is usually just "site", however, you are free to adjust it.
	 * In your `config.ini` write
	 * <code>
	 * [dirs]
	 * site = "site"
	 * </code>
	 * 
	 * @access public
	 * @static
	 * @param string $dir Site directory path
	 * @return void
	 * @see self::getSiteDir()
	 */
	public static function setSiteDir($dir) {
		self::$site_dir = rtrim($dir,'/').'/';
	}

	/**
	 * Returns the site directory.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @see self::setSiteDir()
	 */
	public static function getSiteDir() {
		return self::$site_dir;
	}

	/**
	 * Method for cleaning up the application.
	 *
	 * This function **should be called once a day** via cron scheduler or something similar.
	 * It cleans up the database, expired cache files, obsolete sessions and so forth.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	public static function cleanup() {
		MysqlDb::getInstance()->query(
			// Delete old sessions
			"DELETE FROM sessions WHERE t<'".date('Y-m-d H:i:s', strtotime('-'.Config::get('session','lifetime_days').' days'))."'",
			// Delete abandoned sessionstore values
			'DELETE FROM sessionstore WHERE NOT EXISTS (SELECT sessions.sid FROM sessions WHERE sessions.sid=sessionstore.sid)',
			// Delete expired trace links
			'DELETE FROM links WHERE expires<=NOW()'
		);
		// Empty file cache
		foreach(glob(rtrim(Config::get('dirs', 'cache', true),'/').'/*') as $file) {
			unlink($file);
		}
		// Delete old temp files
		foreach(glob(self::getTempDir().'*') as $file) {
			if(filemtime($file)<time()-86400) {
				unlink($file);
			}
		}
	}

	/**
	 * Adds a resource to the preload chain.
	 *
	 * The $preload resource (e.g. image) will be **loaded with every HTTP request** the client's browser makes.
	 * This makes sure that the resource is available instantaniously.
	 *
	 * - Remember to cache the resource to avoid unnecessary HTTP overhead.
	 * 
	 * @access public
	 * @static
	 * @param string $preload
	 * @return void
	 */
	public static function preload($preload) {
		self::$preload = $preload;
	}

	/**
	 * Initializes the application's class autoloader.
	 *
	 * System classes will be crawled and cached for later use for improved efficiency.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	public static function initAutoloader() {
		foreach(glob(self::getAppDir().'cls/*') as $path) {
			if(preg_match('@^'.self::getAppDir().'cls/([^\.]+)\.php$@', $path, $matches)) {
				if(isset(self::$cls_files[$matches[1]])) Error::fatal('Class is already defined: '.$matches[1]);
				self::$cls_files[$matches[1]] = $path;
			}elseif(is_dir($path)) {
				foreach(glob($path.'/*.php') as $subpath) {
					if(preg_match('@^'.self::getAppDir().'cls/[^/]+/([^\.]+)\.php$@', $subpath, $matches)) {
						if(isset(self::$cls_files[$matches[1]])) Error::fatal('Class is already defined: '.$matches[1]);
						self::$cls_files[$matches[1]] = $subpath;
					}
				}
			}
		}
		spl_autoload_register('App::autoload');
	}

	/**
	 * The actual autoload handler.
	 *
	 * Tries to **find a class that is not yet known**.
	 * You can add a lookup path using
	 * <code>
	 * [dirs]
	 * classes_autoload = "site/cls"
	 * </code>
	 * in your `config.ini`.
	 * 
	 * @access public
	 * @static
	 * @param string $cls
	 * @return void
	 */
	public static function autoload($cls) {
		if(isset(self::$cls_files[$cls])) {
			require_once(self::$cls_files[$cls]);
		}elseif(($cls_dir = Config::get('dirs', 'classes_autoload')) && file_exists($cls_file = $cls_dir.'/'.$cls.'.php')) {
			require_once($cls_file);
		}elseif(count(spl_autoload_functions())<=1) {
			Error::fatal("Class could not be found: $cls");
		}
	}

	/**
	 * Main application initialization.
	 *
	 * Should be **called once with every HTTP request**.
	 * This method loads and prepares all required processes and variables.
	 * Also, the request will be directed to the right handler.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	public static function init() {
		date_default_timezone_set(Config::get('env', 'timezone'));
		if(PHP_SAPI!=='cli') {
			// Handle CDN requests at first
			$cdn_host = Config::get('env', 'cdn_host');
			if($cdn_host && $_SERVER['HTTP_HOST']===$cdn_host) {
				return self::handleCdnRequest();
			}
			// Set fallback protocol and URI prefix
			self::$protocol = isset($_SERVER['HTTPS']) ? 'https://' : '';
			self::$uriprefix = self::$protocol.$_SERVER['HTTP_HOST'].'/';
			// Start session
			self::$session = new Session();
			if(($sandboxed = Config::get('debug', 'sandboxed')) && ($sid = self::getSid())) {
				if(in_array($sid, $sandboxed)) self::$sandboxed = true;
			}
		}
		$skin_functions = self::getSkinPath().'functions.php';
		if(file_exists($skin_functions)) require_once($skin_functions);
		self::$lang = new Language;
		self::$lang->setAutoappend(Config::get('debug', 'lang_autoappend'));
		self::$lang->setBase(Config::get('lang', 'base'));
		if(PHP_SAPI==='cli') {
			// In CLI mode, propper language has to be set manually!
			self::$lang->setLanguage(Config::get('lang','default'));
		}else{
			// When in web context, set propper language
			$lang = self::executeHooks('which-lang');
			if(!self::$lang->setLanguage($lang)) {
				self::$lang->setLanguage(Config::get('lang','default'));
			}
		}
		// Now we have propper localization conditions, update protocol and URI prefix
		self::$protocol = Config::get('env', 'protocol');
		self::$uriprefix = self::$protocol.self::getHost().'/';
		if(PHP_SAPI!=='cli') {
			if(!isset($_COOKIE['r'])) {
				self::executeHooks('first-time-visit');
			}
			setcookie('r', time(), time()+86400 * Config::get('session','returning_days'), '/', Config::get('session','cookiedomain'));
			self::fillMenu();
			if(self::$preload) {
				App::addHook('head',function(){
					$skinpath = App::getSkinPath();
					$css = array_map(function($url) use($skinpath) {
						return 'url("'.App::getCdnUrl($skinpath.$url).'")';
					}, App::$preload);
					return '<style>body.loading:after {content:'.implode(' ',$css).';}</style>';
				});
			}
		}
	}

	/**
	 * Responds to CDN requests.
	 *
	 * ***TODO:*** Handle CDN requests directly with App::handleCdnRequest()
	 * 
	 * @access protected
	 * @static
	 * @return void
	 */
	protected static function handleCdnRequest() {
		echo 'CDN REQUEST'; // TODO
	}

	/** @internal */
	private static function fillMenu() {
		self::addHook('menu',function(){
			$html = '';
			$current = self::getSeofreePage();
			$loggedin = self::getUid();
			foreach(Config::get('menu','main') as $item=>$text) {
				if(strstr($item,':')) {
					list($scope,$item) = explode(':',$item,2);
					if($loggedin && $scope!=='loggedin') continue;
					elseif(!$loggedin && $scope!=='loggedout') continue;
				}
				$html.= '<li class="item_'.trim(preg_replace('@[^a-z0-9]+@','_',strtolower($item)),'_').(rtrim($current,'/')===rtrim($item,'/') ? ' current' : '').'"><a href="'.self::getLink($item).'"><span>'.$text.'</span></a></li>';
			}
			return $html;
		});
	}

	/**
	 * Resolves a URI relative to base URI.
	 *
	 * Base URI can be modified in `config.ini` writing
	 * <code>
	 * [env]
	 * baseuri = "/"
	 * </code>
	 * 
	 * @access public
	 * @static
	 * @param string $uri
	 * @return string
	 */
	public static function resolver($uri) {
		$uri = preg_replace('/\?.*$/','',$uri);
		$base = rtrim(Config::get('env', 'baseuri'),'/');
		return trim(substr($uri,strlen($base)),'/');
	}

	/**
	 * Adds a custom HTML tag handler.
	 *
	 * Handler must be created using and given as argument.
	 * 
	 * @access public
	 * @static
	 * @param CustomHtmlTag $tag
	 * @return void
	 * @see CustomHtmlTag
	 *
	 * @example
	 * <code>
	 * // Custom tag: <custom attr="xyz">
	 * class CustomTag extends CustomHtmlTag {
	 * 	public function __construct() {
	 * 		parent::__construct('custom');
	 * 		$this->attr('attr'); // required attribute
	 * 		$this->attr('class', null); // optional attribute with default fallback
	 * 		$this->setHandler(function($atts){
	 * 			// Deal with $atts
	 * 			return '<div class="custom">'.$atts['attr'].'</div>';
	 * 		});
	 * 	}
	 * }
	 * // Tell application to parse custom HTML tag
	 * App::addCustomHtmlTag(SymbolTag::newInstance());
	 * </code>
	 */
	public static function addCustomHtmlTag(CustomHtmlTag $tag) {
		self::$custom_tags[] = $tag;
	}

	/**
	 * Returns the current version of the application framework.
	 *
	 * If `$product_name` is given, the version number will be prefixed with the name of the application framework.
	 * 
	 * @access public
	 * @static
	 * @param bool $product_name (default: false)
	 * @return string
	 */
	public static function getVersion($product_name=null) {
		return $product_name ? self::PRODUCT.' '.self::VERSION : self::VERSION;
	}

	/**
	 * Main render method, loads propper site templates and renders the requested page.
	 * 
	 * @access public
	 * @static
	 * @param mixed $uri (default: null)
	 * @param bool $return (default: false)
	 * @return void
	 */
	public static function render($uri=null, $return=null) {
		$pages_dir = rtrim(Config::get('dirs', 'pages', true),'/').'/';
		self::$start_time = microtime(true);
		self::$path = self::getSeofreePage();
		$pathok = preg_match('/^[A-Za-z0-9\-_\/\.]+$/',self::$path) && !strstr(self::$path,'..');
		$parts = explode('/',self::$path);
		if($pathok && $parts) self::$query = $parts;
		$template = $parts[0];
		$doc = '';
		for($i=0; $i<count($parts); $i++) {
			$part = $parts[$i];
			if(file_exists($pages_dir.$part.'.php')) {
				$doc = $parts[$i];
				break;
			}
		}
		$contentfile = $pages_dir.$doc.'.php';
		// Start buffered output
		ob_start();
		// Load plugins
		$plugins_dir = Config::get('env', 'plugins_dir');
		if($plugins_dir && ($load = Config::get('plugins', 'load'))) {
			foreach($load as $plugin) {
				$pluginfile = $plugins_dir.'/'.$plugin.'/plugin.php';
				if(file_exists($pluginfile)) {
					require_once($pluginfile);
					$jsfile = $plugins_dir.'/'.$plugin.'/plugin.js';
					if(file_exists($jsfile)) {
						App::addHook('head', function() use($jsfile) {
							return '<script src="'.$jsfile.'"></script>';
						});
					}
				}else{
					Error::warning('Plugin could not be loaded: '.$plugin);
				}
			}
		}
		// Load head
		$tpl_head_file = self::getSkinPath().'tpl-head.php';
		include($tpl_head_file);
		// Load content
		if($pathok && file_exists($contentfile)) {
			require_once($contentfile);
		}elseif(!$pathok) {
			require_once($pages_dir.'+start.php');
		}elseif(preg_match('/\.(jpe?g|gif|png)$/i',self::$path)) {
			require_once($pages_dir.'+404+image.php');
		}else{
			require_once($pages_dir.'+404.php');
		}
		// Load foot
		$tpl_foot_file = self::getSkinPath().'tpl-foot.php';
		include($tpl_foot_file);
		// Notification handling
		if(Notify::hasMessages()) {
			self::addHook('after-footer',function(){
				return '<script type="text/javascript" id="notifyscript">'."\napp.notify.query();\n</script>\n";
			});
		}
		if(!Config::get('env', 'skip_generator')) {
			self::addHook('head',function(){
				return '<meta name="Generator" content="'.self::getVersion(true).'">'."\n";
			});
		}
		// End buffered output
		$html = ob_get_clean();
		$html = self::renderReplacements($html);
		// Debug information
		if(Config::get('debug')) {
			$html = self::renderDebugInformation($html);
		}
		// Final HTML output
		if($return) return $html;
		else echo $html;
	}

	/**
	 * Renders HTML replacements.
	 *
	 * Replaces things like `[[[TITLE]]]` and builds up the row and box system.
	 * Hooks (`[[[HOOK:xyz]]]`) are rendered / executed too, as well as custom tags.
	 * If CDN is set, resource links are also replaced.
	 * 
	 * @access public
	 * @static
	 * @param string $html
	 * @return string
	 * @see self::addHook()
	 * @see self::addCustomHtmlTag()
	 * @see self::getCdnUrl()
	 */
	public static function renderReplacements($html) {
		// Insert title and description
		if(self::$title) {
			$html = str_replace('[[[TITLE]]]',self::getTitle(),$html);
			$canonical = strpos(self::getSeofreePage(), strtolower(self::$title))===false ? self::getLink(self::getSeofreePage()) : self::getLink(self::getSeofreePage(), self::$title);
			$html = str_replace('[[[CANONICAL]]]',$canonical,$html);
		}else{
			$html = str_replace('[[[TITLE]]]',Config::get('htmlhead','title'),$html);
			$html = str_replace('[[[CANONICAL]]]',self::getLink(self::getPage()),$html);
		}
		$html = str_replace('[[[LANG]]]',self::getLang(),$html);
		$html = str_replace('[[[DESCRIPTION]]]',Config::get('htmlhead','description'),$html);
		$html = str_replace('[[[BODYCLASS]]]', self::getLang().' '.(self::getPage(0) ? 'page-'.self::getPage(0) : 'page-start').' '.(count(self::getPage())>1 ? 'sub' : 'root').' '.(self::isSandboxed() ? 'sandboxed' : 'production'),$html);
		$html = preg_replace_callback('/\[\[\[HOOK:([^\]]+)\]\]\]\n?/', function($matches){
			return self::executeHooks($matches[1]);
		}, $html);
		// Layout elements
		$html = preg_replace_callback('@<row([^>]*)>@i',function($match){
			$class = 'row';
			$attr = $match[1];
			if(!trim($attr)) return '<div class="'.$class.'">';
			if(preg_match('@\sclass\s*=\s*["\']([^"\']+)["\']@i',$attr,$matches)) {
				$class_add = trim(preg_replace('/\s+/',' ',$matches[1]));
				if($class_add) $class.= ' '.$class_add;
				$attr = preg_replace('/\s*class=["\'][^"\']+["\']/i','',$attr);
			}
			return '<div class="'.$class.'"'.$attr.'>';
		},$html);
		$html = str_ireplace('</row>','</div><div class="clearRow"></div>',$html);
		$html = preg_replace_callback('@<box([^>]*)>@i',function($match){
			$class = array('box');
			$attr = $match[1];
			if(preg_match('@\ssize\s*=\s*["\']([^"\']+)["\']@i',$match[0],$matches)) {
				$attr = str_replace($matches[0],'',$attr);
				$size = $matches[1];
				switch(preg_replace('@[^0-9/]+@','',$size)) {
					case '1/4':
					case '2/8':
						$size = 'one-fourth'; break;
					case '1/3':
					case '2/6':
						$size = 'one-third'; break;
					case '1/2':
					case '2/4':
					case '4/8':
						$size = 'one-half'; break;
					case '2/3':
					case '4/6':
						$size = 'two-thirds'; break;
					case '3/4':
					case '6/8':
						$size = 'three-fourths'; break;
					case '1/5':
						$size = 'one-fifth'; break;
					case '2/5':
						$size = 'two-fifth'; break;
					case '3/5':
						$size = 'three-fifth'; break;
					case '4/5':
						$size = 'four-fifth'; break;
					case '1/6':
						$size = 'one-sixth'; break;
					case '2/6':
						$size = 'two-sixth'; break;
					case '3/6':
						$size = 'three-sixth'; break;
					case '4/6':
						$size = 'four-sixth'; break;
					case '5/6':
						$size = 'five-sixth'; break;
					case '1/7':
						$size = 'one-seventh'; break;
					case '2/7':
						$size = 'two-seventh'; break;
					case '3/7':
						$size = 'three-seventh'; break;
					case '4/7':
						$size = 'four-seventh'; break;
					case '5/7':
						$size = 'five-seventh'; break;
					case '6/7':
						$size = 'six-seventh'; break;
					case '1/8':
						$size = 'one-eigth'; break;
					case '3/8':
						$size = 'three-eigth'; break;
					case '5/8':
						$size = 'five-eigth'; break;
					case '7/8':
						$size = 'seven-eigth'; break;
					case '1/1':
					case '1':
						$size = 'fullwidth'; break;
				}
				$class[] = $size;
			}else{
				$class[] = 'fullwidth';
			}
			$class = implode(' ',$class);
			if(preg_match('@\sclass\s*=\s*["\']([^"\']+)["\']@i',$attr,$matches)) {
				$class_add = trim(preg_replace('/\s+/',' ',$matches[1]));
				if($class_add) $class.= ' '.$class_add;
				$attr = preg_replace('/\s*class=["\'][^"\']+["\']/i','',$attr);
			}
			return '<div class="'.$class.'"'.$attr.'>';
		},$html);
		$html = str_ireplace('</box>','</div>',$html);
		// Replace custom HTML tags
		foreach(self::$custom_tags as $customtag) {
			$html = CustomHtmlTag::renderReplacement($html, $customtag);
		}
		// Overall translation
		$html = self::$lang->translateHtml($html);
		if(!Config::get('debug')) {
			// CDN replacements
			$html = self::renderCdnReplacements($html);
			// Optimize
			$html = preg_replace(array('@\n+\s*@','@[\t ]+@'),array("\n",' '),$html);
			$html = str_replace("\n<", '<', $html);
			$html = substr_replace($html, "\n", strpos($html,'>')+1, 0);
			$html = trim($html);
		}
		// We're done
		return $html;
	}

	/**
	 * Returns the CDN URL if available.
	 *
	 * You can set the CDN handling domain by writing it into `config.ini`:
	 * <code>
	 * [env]
	 * cdn_host = "cdn.example.com"
	 * </code>
	 * 
	 * @access public
	 * @static
	 * @param string $url
	 * @return string
	 */
	public static function getCdnUrl($url) {
		$cdn_host = Config::get('env', 'cdn_host');
		if(!$cdn_host) return $url;
		return '//'.$cdn_host.'/'.strtr(rtrim(base64_encode($url),'='),'+/','-_');
	}

	/**
	 * Returns HTML with resource links replaced by CDN versions.
	 *
	 * If no CDN host is set, the HTML will be returned without modification.
	 * 
	 * @access public
	 * @static
	 * @param string $html
	 * @return string
	 * @see self::getCdnUrl()
	 */
	public static function renderCdnReplacements($html) {
		$cdn_host = Config::get('env', 'cdn_host');
		if(!$cdn_host) return $html;
		// links
		$html = self::replaceUrisInHtmlToCdnVersion($html, 'link', 'href', '\.(css|png|jpg|jpeg|gif|svg|json)', ['App','getCdnUrl']);
		// images
		$html = self::replaceUrisInHtmlToCdnVersion($html, 'img', 'src', '\.(png|jpg|jpeg|gif|svg)', ['App','getCdnUrl']);
		// scripts
		$html = self::replaceTags($html, '<script([^>]+)></script>', function($matches){
			if(!preg_match('@\ssrc=["\']([^"\']+)["\']@', $matches[1], $src)) {
				return $matches[0];
			}
			App::$js_files[] = $src[1];
			return '';
		});
		if(self::$js_files) {
			$html = str_replace('</head>', '<script src="'.self::getCdnUrl(implode('***',self::$js_files)).'"></script></head>', $html);
		}
		// done
		return $html;
	}

	/** @internal */
	protected static function replaceTags($html, $tag_regex, callable $replace_callback) {
		return preg_replace_callback("@$tag_regex@", function($matches) use ($replace_callback) {
			return call_user_func($replace_callback, $matches);
		}, $html);
	}

	/** @internal */
	protected static function replaceUrisInHtmlToCdnVersion($html, $tag, $attr, $regex_ends, callable $replace_callback) {
		return preg_replace_callback("@<$tag (.*?)$attr=([\"'])([^\"']+)[\"']([^>]*)>@", function($matches) use ($tag, $attr, $regex_ends, $replace_callback) {
			if($regex_ends && !preg_match('@'.$regex_ends.'($|\?)@i', $matches[3])) {
				return $matches[0];
			}
			if(strstr($matches[3],'//') && !strstr($matches[3],'//'.Config::get('env', 'host').'/')) return $matches[0];
			$html = '<'.$tag.' '.$matches[1].$attr.'='.$matches[2].call_user_func($replace_callback, $matches[3]).$matches[2].$matches[4].'>';
			if(Config::get('debug')) return '<!--CDN-->'.$html.'<!--/CDN-->';
			return $html;
		}, $html);
	}

	/** @internal */
	protected static function renderDebugInformation($html) {
		$sec = round(microtime(true)-self::$start_time,3).'000';
		$dot = strpos($sec,'.');
		$sec = '<b>'.substr($sec,0,$dot).'.'.substr($sec,$dot+1,1).'</b>'.substr($sec,$dot+2,2);
		$html = str_replace('</body>','<div id="debug-info" style="position: fixed; left: 0; top: 0; background: #000; color: #fff; padding: 0.3em 1em 0.6em 0.3em; opacity: 0.4; white-space: nowrap; max-height: 50%; max-width: 50%; overflow: auto;"><span style="font-size: 0.8em;">Processing time</span><br>'.$sec.' s</div>'."\n".'</body>',$html);
		return $html;
	}

	/**
	 * Renders ajax requests.
	 * 
	 * @access public
	 * @static
	 * @param string $uri (default: null)
	 * @param bool $return (default: false)
	 * @return void
	 */
	public static function renderajax($uri=null, $return=null) {
		self::$path = self::resolver($uri===null ? $_SERVER['REQUEST_URI'] : $uri);
		$class = substr(self::$path,5);
		$class = preg_replace('/[^A-Za-z]+/','',$class);
		if($class && is_callable(array($class,'ajax'))) {
			try {
				$ajax = $class::ajax(explode('/',trim($class,'/')),array_merge($_POST,$_GET));
				if($return) return $ajax;
				echo (is_array($ajax) || is_object($ajax)) ? json_encode($ajax) : $ajax;
			}catch(Exception $e){
				Error::warning($e);
			}
		}
	}

	/**
	 * Converts arbitrary text to URL safe version.
	 * 
	 * @access public
	 * @static
	 * @param string $str
	 * @return string
	 *
	 * @example
	 * <code>
	 * App::seostr('Münster Land') // This becomes 'Muenster-Land'
	 * </code>
	 */
	public static function seostr($str) {
		$str = str_replace(array('Ä','Ö','Ü','ä','ö','ü','ß'),array('Ae','Oe','Ue','ae','oe','ue','ss'),$str);
		$str = strtr(utf8_decode($str),utf8_decode('ŠŒŽšœžŸ¥µÀÁÂÃÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕØÙÚÛÝàáâãåæçèéêëìíîïðñòóôõøùúûýÿ'),'SOZsozYYuAAAAAACEEEEIIIIDNOOOOOUUUYaaaaaaceeeeiiiionooooouuuyy');
		$str = trim(preg_replace('/\W+/','-',$str),'-');
		return preg_replace('/[^A-Za-z0-9_-]/','',$str);
	}

	/**
	 * Returns the current URL without SEO string.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @see self::link()
	 * @see self::getLink()
	 */
	public static function getSeofreePage() {
		$page = self::resolver($_SERVER["REQUEST_URI"]);
		return preg_replace('@/_/[^/]+/?$@','',$page);
	}
	
	/**
	 * Prints (or returns) the URL to a specific page.
	 * 
	 * @access public
	 * @static
	 * @param string $page The page identifier
	 * @param string $seostr (optional) A string attached at the end of the URL (default: null)
	 * @param bool $return (optional) If set to true, the URL is returned instead of written to the output (default: false)
	 * @return void
	 * @see self::getLink()
	 */
	public static function link($page, $seostr=null, $return=null) {
		$link = rtrim($page,'/');
		if($seostr) $link.= '/_/'.self::seostr($seostr);
		$link = self::$uriprefix.ltrim($link,'/');
		if($return) return $link;
		echo $link;
	}

	/**
	 * Returns an URL for a page or the current page.
	 * 
	 * @access public
	 * @static
	 * @param string $page (optional) A page identifier. If omitted, the current page will be used. (default: null)
	 * @param string $seostr (optional) A string attached at the end of the URL (default: null)
	 * @return string
	 * @see self::link()
	 *
	 * @example
	 * <code>
	 * echo '<a href="'.App::getLink('mypage').'">My Link</a>';
	 * </code>
	 */
	public static function getLink($page=null, $seostr=null) {
		if($page===null) $page = self::getPage();
		return self::link($page,$seostr,true);
	}

	/**
	 * Writes an URL for switching the language to `$newlang` to the output.
	 * 
	 * @access public
	 * @static
	 * @param string $newlang The new language identifier
	 * @return void
	 *
	 * @example
	 * <code>
	 * App::switchLangLink('en')
	 * </code>
	 */
	public static function switchLangLink($newlang) {
		if(self::getLang()===$newlang) {
			$uri = self::getPage().'/';
		}else{
			$uri = self::getSeofreePage($uri).'/';
		}
		$link = self::$protocol.$newlang.substr($_SERVER['HTTP_HOST'],strpos($_SERVER['HTTP_HOST'],'.')).'/'.ltrim($uri,'/');
		echo $link;
	}

	/**
	 * Defines the title of the HTML page.
	 *
	 * If applicable, the title suffix will be appended.
	 * 
	 * @access public
	 * @static
	 * @param string $str The title
	 * @param bool $skip_suffix (optional) If set to true, no title suffix will be appended. (default: false)
	 * @return void
	 */
	public static function setTitle($str, $skip_suffix=null) {
		self::$title = trim($str);
		self::$title_skip_suffix = $skip_suffix;
	}

	/**
	 * Returns the page title previously set.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getTitle() {
		return self::$title.(self::$title_skip_suffix ? '' : ' '.trim(Config::get('htmlhead','titlesuffix')));
	}

	/**
	 * Renders a widget.
	 * 
	 * @access public
	 * @static
	 * @param string $name The widget identifier
	 * @return void
	 */
	public static function widget($name) {
		include(rtrim(Config::get('dirs', 'widgets', true),'/').'/'.$name.'.php');
	}

	/**
	 * Returns the current page's URI parts or one specific part.
	 *
	 * This method is error safe, which means if the given part number does not exist, `null` will be returned and no error will be triggered.
	 *
	 * @access public
	 * @static
	 * @param integer $part (optional) The part number (default: null)
	 * @return mixed
	 *
	 * @example
	 * <code>
	 * //Current URL: http://www.example.com/about/more/info
	 * App::getPage(); // returns (array) ['about', 'more', 'info']
	 * App::getPage(0); // returns (string) 'about'
	 * App::getPage(2); // returns (string) 'info'
	 * App::getPage(99); // returns null
	 * </code>
	 */
	public static function getPage($part=null) {
		$page = self::getSeofreePage();
		if($part===null) return $page;
		$page = explode('/',$page);
		return isset($page[$part]) ? $page[$part] : null;
	}

	/**
	 * Builds up the URI of the current page.
	 *
	 * @access public
	 * @static
	 * @param array $get (optional) If set, these GET parameters are attached (default: array())
	 * @return string
	 *
	 * @example
	 * <code>
	 * // Current URL: http://www.example.com/about/more/info
	 * App::getUri(); // returns 'about/more/info'
	 * App::getUri(['name' => 'John']); // returns 'about/more/info?name=John'
	 * </code>
	 */
	public static function getUri(array $get=array()) {
		$uri = self::getPage();
		if($get) {
			if(is_array($get)) $uri.= '?'.http_build_query($get);
			else $uri.= '?'.$get;
		}
		return $uri;
	}

	/**
	 * Returns the current `Language` object.
	 * 
	 * @access public
	 * @static
	 * @return Language
	 */
	public static function getLang() {
		return self::$lang;
	}

	/**
	 * Returns an array containing all available language codes.
	 * 
	 * @access public
	 * @static
	 * @return array
	 */
	public static function getLanguages() {
		$lang_dir = rtrim(Config::get('dirs', 'lang', true),'/').'/';
		if(!self::$languages) {
			self::$languages = glob($lang_dir.'*.csv');
			foreach(self::$languages as $key=>$lang) {
				self::$languages[$key] = substr($lang,5,2);
			}
		}
		return self::$languages;
	}

	/**
	 * Returns the current `Session` object.
	 * 
	 * @access public
	 * @static
	 * @return Session
	 */
	public static function getSession() {
		if(!self::$session) return null;
		return self::$session;
	}

	/**
	 * Returns the current `Session` ID.
	 *
	 * This method is failsafe, which means if no `Session` ID can be found, `null` will be returned and no error will be triggered.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getSid() {
		if(!self::$session) return null;
		return self::$session->getSid();
	}

	/**
	 * Returns the current `User` ID if a user is logged in.
	 *
	 * This method is failsafe, which means if no `User` ID can be found, `null` will be returned and no error will be triggered.
	 * This function can be used to check if a user is currently logged in.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @deprecated Use User::getSessionUid() instead
	 */
	public static function getUid() {
		Error::deprecated('User::getSessionUid()');
		return User::getSessionUid();
	}

	/**
	 * getUrl function.
	 * 
	 * @access public
	 * @static
	 * @param bool $urlencode (default: false)
	 * @return string
	 */
	public static function getUrl($urlencode=null) {
		$url = $_SERVER["REQUEST_URI"];
		if($pos = strpos($url,'?')) $url = substr($url,0,$pos-1);
		return $urlencode ? urlencode($url) : $url;
	}

	/**
	 * Erases the entire output buffer.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	public static function clear() {
		ob_end_clean();
	}

	/**
	 * Causes the application to stop.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	public static function halt() {
		die();
	}

	/**
	 * Redirects the user to a specified URL.
	 *
	 * This function clears every output and halts the application after execution.
	 * 
	 * @access public
	 * @static
	 * @param string $url (optional) The URL to redirect to. (default: '/')
	 * @param bool $fullurl If set to false, an internal page is assumed (default: false)
	 * @return void
	 * @see self::clear()
	 * @see self::halt()
	 */
	public static function redirect($url=null, $fullurl=false) {
		self::clear();
		if($url===null) $url = '/';
		if(!$fullurl) {
			$url = App::getLink($url);
		}
		header("Location: $url");
		self::halt();
	}

	/**
	 * Causes the current location to be reloaded.
	 * 
	 * @access public
	 * @static
	 * @return void
	 * @deprecated
	 */
	public static function refresh() {
		Error::deprecated();
		self::clear();
		header("Location: ".self::getUrl());
		self::halt();
	}

	/**
	 * Writes / returns the absolute URL of the given `$path`.
	 * 
	 * @access public
	 * @static
	 * @param string $path The relative file path
	 * @param bool $return If true, returns the absolute URL instead of writing it to the output (default: false)
	 * @return void
	 * @see self::linkVersionedFile()
	 *
	 * @example
	 * <code>
	 * App::linkFile('lib/script.js'); // writes 'http://www.example.com/lib/script.js' to the output
	 * </code>
	 */
	public static function linkFile($path, $return=false) {
		$link = Config::get('env', 'baseuri').trim($path,'/');
		if($return) return $link;
		echo $link;
	}

	/**
	 * Writes / returns a versioned absolute URL of the given file `$path`.
	 *
	 * This function appends the file modified time to make sure no old cached version will be delivered to the visitor.
	 *
	 * @access public
	 * @static
	 * @param string $path The relative file path
	 * @param bool $return If true, returns the absolute URL instead of writing it to the output (default: false)
	 * @return void
	 * @see self::linkFile()
	 *
	 * @example
	 * <code>
	 * App::linkVersionedFile('lib/script.js'); // writes 'http://www.example.com/lib/script.js?t=946681200' to the output
	 * </code>
	 */
	public static function linkVersionedFile($path, $return=false) {
		$link = self::linkFile($path,true).'?t='.filemtime($path);
		if($return) return $link;
		echo $link;
	}

	/**
	 * Returns the relative path to the current skin.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getSkinPath() {
		return 'skins/'.Config::get('env', 'skin').'/';
	}

	/**
	 * Returns the absolute path to the temp directory.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getTempDir() {
		$dir = realpath(Config::get('dirs', 'temp', true));
		return rtrim($dir,'/').'/';
	}

	/**
	 * Returns the path to user uploaded files meant for permanent storage.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @deprecated Use User::getUploadDir() instead
	 */
	public static function getUserUploadDir() {
		Error::deprecated('User::getUploadDir()');
		return User::getUploadDir();
	}

	/**
	 * Creates a temp file and returns its path.
	 * 
	 * @access public
	 * @static
	 * @param string $prefix The prefix of the temp file.
	 * @return string
	 */
	public static function createTempFile($prefix) {
		$tempfile = tempnam(self::getTempDir(), $prefix.'_');
		chmod($tempfile, 0777);
		return $tempfile;
	}

	/**
	 * Creates a file in the user upload directory and returns its path.
	 * 
	 * @access public
	 * @static
	 * @param string $suffix The suffix of the file.
	 * @return string
	 * @deprecated Use User::createUploadFile() instead
	 */
	public static function createUserUploadFile($suffix) {
		Error::deprecated('User::createUploadFile()');
		return User::createUploadFile();
	}

	/**
	 * processLinkTrackerAction function.
	 * 
	 * @access public
	 * @static
	 * @return void
	 * @deprecated Use LinkTracker::action(App::getPage(1)) instead
	 */
	public static function processLinkTrackerAction() {
		Error::deprecated('LinkTracker::processAction()');
		LinkTracker::action(self::getPage(1));
	}

	/**
	 * Executes hooks for the given `$id`.
	 * 
	 * @access public
	 * @static
	 * @param string $id
	 * @param mixed $param (default: null)
	 * @return string
	 * @see self::addHook()
	 */
	public static function executeHooks($id, $param=null) {
		if(!isset(self::$hooks[$id])) return;
		$result = '';
		foreach(self::$hooks[$id] as $function) {
			$result.= $function($param);
		}
		return $result;
	}

	/**
	 * Adds a hook function.
	 * 
	 * @access public
	 * @static
	 * @param string $id The hook id
	 * @param function $function An executable function or class method reference
	 * @return void
	 * @see self::executeHooks()
	 */
	public static function addHook($id, callable $function) {
		if(!isset(self::$hooks[$id])) self::$hooks[$id] = array();
		self::$hooks[$id][] = $function;
	}

	/**
	 * Sends a templated email.
	 * 
	 * @access public
	 * @static
	 * @param string $email The email address
	 * @param string $firstname Recipient's first name
	 * @param string $lastname Recipient's last name
	 * @param string $subject Mailing subject
	 * @param string $body The email message (HTML format)
	 * @param array $attachments (optional) A list of `EMailAttachment` objects (default: array())
	 * @return bool false on error, otherwise true
	 */
	public static function sendCustomerMail($email, $firstname, $lastname, $subject, $body, array $attachments=array()) {
		$subject = self::$lang->translateHtml($subject);
		$body = self::$lang->translateHtml($body);
		$mail = new EMail;
		$mail->setHtmlTemplate(App::getSkinPath().'email/main-'.App::getLang().'.tpl');
		$mail->setFrom(Config::get('email', 'from_address'), Config::get('email', 'from_name'));
		$mail->addTo($email, $firstname.' '.$lastname);
		$mail->setSubject($subject);
		$mail->applyTemplates(array(
			'SUBJECT' => $subject,
			'FIRSTNAME' => $firstname,
			'BODY' => $body,
		), false);
		foreach($attachments as $att) {
			$mail->addAttachment($att);
		}
		if($result = $mail->send()) {
			$db = MysqlDb::getInstance();
			$db->query($db->prepare("INSERT INTO `emails` SET `recipient`=@VAL, `subject`=@VAL", $email, $subject));
		}else{
			Error::warning('E-mail could not be sent. Recipient: '.$email.', Subject: '.$subject);
		}
		return $result;
	}

	/**
	 * Checks if the current session is in sandbox mode.
	 * 
	 * @access public
	 * @static
	 * @return bool
	 */
	public static function isSandboxed() {
		return self::$sandboxed;
	}

	/**
	 * Temporarly sets the sandbox mode for the current session.
	 * 
	 * @access public
	 * @static
	 * @param bool $sandboxed (default: true)
	 * @return void
	 */
	public static function setSandboxed($sandboxed=null) {
		self::$sandboxed = (bool) $sandboxed;
	}

	/**
	 * This function should be called by a cron scheduler script.
	 * 
	 * @access public
	 * @static
	 * @param string $period The period identifier (something like 'daily'; can be defined freely)
	 * @return void
	 * @see self::addHook()
	 *
	 * @example
	 * <code>
	 * // Define an action in your skin's functions.php
	 * App::addHook('cron', function($period){
	 * 	// Do something
	 * });
	 * // Let cron execute the actions
	 * App::cron('daily');
	 * </code>
	 */
	public static function cron($period) {
		if(!$period) return;
		self::executeHooks('cron', $period);
	}

	/**
	 * Send an email notification to the website administrator.
	 * 
	 * @access public
	 * @static
	 * @param string $msg The email message
	 * @param string $subject (optional) The email subject (default: 'Admin Notification')
	 * @return void
	 */
	public static function adminNotification($msg, $subject=null) {
		if(!$subject) $subject = 'Admin Notification';
		mail(Config::get('email', 'admin_notify_addr'), $subject, $msg, "From: ".Config::get('email', 'admin_notify_addr')."\nContent-Type: text/plain; charset=utf-8");
	}

	/**
	 * Returns the actual HTTP hostname, filtered by hook `'get-host'`.
	 * 
	 * @access public
	 * @static
	 * @return string
	 * @see self::addHook()
	 */
	public static function getHost() {
		if(self::$host) return self::$host;
		$host = Config::get('env', 'host');
		if($host_hooked = App::executeHooks('get-host', $host)) {
			$host = $host_hooked;
		}
		self::$host = $host;
		return $host;
	}

}
