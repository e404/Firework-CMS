<?php

require_once(__DIR__.'/../Abstracts/Inject.php');

/**
 * <u>Firework CMS</u> (Main Application).
 *
 * @copyright Roadfamily LLC, 2016
 * @license ../license.txt
 */
class App {

	use Inject;

	/** @internal */
	const PRODUCT = 'Firework CMS';
	/** @internal */
	const VERSION = '1.2.0';

	/** @internal */
	final public function __construct() {
		Error::fatal('Trying to instantiate a non-instantiable class.');
	}

	protected static $start_time;
	protected static $app_dir = './';
	protected static $site_dir = 'site/';
	protected static $path = null;
	protected static $languages = array();
	protected static $lang = null;
	protected static $session = null;
	protected static $query = array();
	protected static $title = '';
	protected static $title_skip_suffix = false;
	protected static $protocol = 'http://';
	protected static $host = '';
	protected static $uriprefix = '';
	protected static $preload = array();
	protected static $hooks = array();
	protected static $cls_files = array();
	protected static $sandboxed = false;
	protected static $custom_tags = array();
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
	 * Sets the site directory and adds it to the include path.
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
		set_include_path(get_include_path().PATH_SEPARATOR.self::$site_dir);
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
		// Delete old temp files
		foreach(glob(self::getTempDir().'*') as $file) {
			if(time()>filectime($file)+86400) {
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
	 * @param mixed $preload
	 * @return void
	 */
	public static function preload($preload) {
		if(is_array($preload)) {
			self::$preload = array_merge(self::$preload, $preload);
		}else{
			self::$preload[] = $preload;
		}
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
		if(class_exists('Config') && ($cls_dir = Config::get('dirs', 'classes_autoload')) && file_exists($cls_file = $cls_dir.'/'.$cls.'.php')) {
			require_once($cls_file);
		}elseif(isset(self::$cls_files[$cls])) {
			require_once(self::$cls_files[$cls]);
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
				if($sandboxed===true || (is_array($sandboxed) && in_array($sid, $sandboxed))) self::$sandboxed = true;
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
			if(Config::get('env', 'protocol')==='https://' && !isset($_SERVER['HTTPS']) && Config::get('env', 'force_tls')) {
				self::redirect(self::getLink(), true);
			}
			if(!isset($_COOKIE['r'])) {
				self::executeHooks('first-time-visit');
			}
			setcookie('r', time(), time()+86400 * Config::get('session','returning_days'), '/', Config::get('session','cookiedomain'));
			// Make sure webcron gets executed if enabled
			if(Config::get('env', 'webcron')) {
				if(self::$session) {
					$last_webcron_exec = self::$session->get('webcron_exec');
					if(!$last_webcron_exec || time()>$last_webcron_exec+3600) {
						App::addHook('head', function(){
							App::getSession()->set('webcron_exec', time());
							return "<script>app.webcron.execute();</script>\n";
						});
					}
				}
			}
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
		// Make sure the site dir is added to the include path
		set_include_path(get_include_path().PATH_SEPARATOR.self::getSiteDir());
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

	private static function fillMenu() {
		self::addHook('menu',function(){
			$html = '';
			$current = self::getSeofreePage();
			$loggedin = User::getSessionUid();
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

	/** @internal */
	protected static function spawn($__phpfile) {
		call_user_func(function() use ($__phpfile){
			require($__phpfile);
		});
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
		$plugins_dir = Config::get('dirs', 'plugins');
		if($plugins_dir && ($load = Config::get('plugins', 'load'))) {
			foreach($load as $plugin) {
				$pluginfile = $plugins_dir.'/'.$plugin.'/plugin.php';
				if(file_exists($pluginfile)) {
					self::spawn($pluginfile);
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
			self::spawn($contentfile);
		}elseif(!$pathok) {
			self::spawn($pages_dir.'+start.php');
		}elseif(preg_match('/\.(jpe?g|gif|png)$/i',self::$path)) {
			self::spawn($pages_dir.'+404+image.php');
		}else{
			self::spawn($pages_dir.'+404.php');
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
			$html = str_replace('[[[CANONICAL]]]',self::getLink(self::getUrlPart()),$html);
		}
		$html = str_replace('[[[LANG]]]',self::getLang(),$html);
		$html = str_replace('[[[DESCRIPTION]]]',Config::get('htmlhead','description'),$html);
		$html = str_replace('[[[BODYCLASS]]]', self::getLang().' '.(self::getUrlPart(0) ? 'page-'.self::getUrlPart(0) : 'page-start').' '.(count(self::getUrlPart())>1 ? 'sub' : 'root').' '.(self::isSandboxed() ? 'sandboxed' : 'production').' '.(User::getSessionUid() ? 'loggedin' : 'loggedout'),$html);
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
			if(Config::get('env', 'optimize_html')) {
				// Optimize HTML output
				$html = preg_replace(array('@\n+\s*@','@[\t ]+@'),array("\n",' '),$html);
				// TODO: This does not work:
				$html = preg_replace('@\s*</(div|p|br|h[1-6]|address|article|aside|audio|video|blockquote|canvas|dd|dl|fieldset|footer|form|header|hgroup|hr|noscript|ol|output|pre|section|table|tfoot|ul|figure|figcaption|body|html|meta|link)>\s*@', '<$1>', $html);
				$html = preg_replace('@\s+<(br>\s*|div|p|h[1-6]|address|article|aside|audio|video|blockquote|canvas|dd|dl|fieldset|footer|form|header|hgroup|hr|noscript|ol|output|pre|section|table|tfoot|ul|figure|figcaption|body|html|meta|link)@', '<$1', $html);
				// END TODO
				$html = substr_replace($html, "\n", strpos($html,'>')+1, 0);
				$html = trim($html);
			}
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

	protected static function replaceTags($html, $tag_regex, callable $replace_callback) {
		return preg_replace_callback("@$tag_regex@", function($matches) use ($replace_callback) {
			return call_user_func($replace_callback, $matches);
		}, $html);
	}

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

	protected static function renderDebugInformation($html) {
		$sec = round(microtime(true)-self::$start_time,3).'000';
		$dot = strpos($sec,'.');
		$sec = '<b>'.substr($sec,0,$dot).'.'.substr($sec,$dot+1,1).'</b>'.substr($sec,$dot+2,2);
		$html = str_replace('</body>','<div id="debug-info" style="position: fixed; z-index: 80000000; left: 0; bottom: 0; background: rgb(0, 0, 0); color: rgb(255, 255, 255); padding: 0.3em 1em 0.6em 0.3em; opacity: 0.1; white-space: nowrap; max-height: 50%; max-width: 50%; overflow: auto;" onmouseover="this.style.opacity=0.5" onmouseout="this.style.opacity=0.1"><span style="font-size: 0.8em;">Processing time</span><br>'.$sec.' s</div>'."\n".'</body>',$html);
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
		$class = preg_replace('/[^A-Za-z0-9_]+/','',$class);
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
		if($page===null) $page = self::getUrlPart();
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
			$uri = self::getUrlPart().'/';
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
	 * ***DEPRECATED*** Returns the current page's URI parts or one specific part.

	 * @access public
	 * @static
	 * @param integer $part (optional) The part number (default: null)
	 * @return mixed
	 * @deprecated Use App::getUrlPart() instead
	 * @see self::getUrlPart()
	 */
	public static function getPage($part=null) {
		Error::deprecated('getUrlPart()');
		return self::getUrlPart($part);
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
	 * App::getUrlPart(); // returns (array) ['about', 'more', 'info']
	 * App::getUrlPart(0); // returns (string) 'about'
	 * App::getUrlPart(2); // returns (string) 'info'
	 * App::getUrlPart(99); // returns null
	 * </code>
	 */
	public static function getUrlPart($part=null) {
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
		$uri = self::getUrlPart();
		if($get) {
			if(is_array($get)) $uri.= '?'.http_build_query($get);
			else $uri.= '?'.$get;
		}
		return $uri;
	}

	/**
	 * Manually sets the language.
	 * 
	 * @access public
	 * @static
	 * @param mixed $lang Either 2 character ISO language string or `Language` object.
	 * @return Language
	 */
	public static function setLang($lang) {
		if(is_string($lang)) {
			return self::$lang->setLanguage($lang);
		}elseif($lang instanceof Language) {
			self::$lang = $lang;
			return true;
		}else{
			Error::warning('Language could not be set.');
			return false;
		}
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
		ob_start();
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
		return self::getSiteDir().'skins/'.Config::get('env', 'skin').'/';
	}

	/**
	 * Returns the absolute path to the temp directory.
	 * 
	 * @access public
	 * @static
	 * @return string
	 */
	public static function getTempDir() {
		return rtrim(realpath('cache'),'/').'/';
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
	 * Executes hooks for the given `$id`.
	 * 
	 * @access public
	 * @static
	 * @param string $id
	 * @param mixed $param (default: null)
	 * @return string
	 * @see self::addHook()
	 * @see self::hasHooks()
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
	 * @see self::hasHooks()
	 */
	public static function addHook($id, callable $function) {
		if(!isset(self::$hooks[$id])) self::$hooks[$id] = array();
		self::$hooks[$id][] = $function;
	}

	/**
	 * Checks if there are hooks for a certain id.
	 * 
	 * @access public
	 * @static
	 * @param string $id The hook id
	 * @return mixed The number of the hooks or `false` if none
	 * @see self::addHook()
	 * @see self::executeHooks()
	 */
	public static function hasHooks($id) {
		if(!isset(self::$hooks[$id])) return false;
		return count(self::$hooks[$id]);
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
	 * Alternatively to cron, webcron can be used
	 * 
	 * Webcron can be enables via config.ini setting.
	 * <code>
	 * [env]
	 * webcron = true
	 * </code>
	 * 
	 * @access public
	 * @static
	 * @return void
	 * @see self::addHook()
	 * @see self::cron()
	 * 
	 * The webcron hook will be called automatically on every first visit and after every hour or with a minimum interval set via config.ini setting.
	 * <code>
	 * [env]
	 * webcron_interval = 600
	 * </code>
	 *
	 * ***Warning***: This is no reliable method for executing functions that require timing.
	 * All assigned webcron functions will not be called at all when the site encounteres no page visits.
	 *
	 * @example
	 * <code>
	 * // Make sure webcron is enabled
	 * // Define a webcron action in your skin's functions.php
	 * App::addHook('webcron', function(){
	 * 	// Do something
	 * });
	 * </code>
	 */
	public static function webcron() {
		if(!App::hasHooks('webcron') || !Config::get('env', 'webcron')) return;
		$min_interval = Config::get('env', 'webcron_interval');
		if(!$min_interval) $min_interval = 3600;
		$last_webcron_exec = Cache::get('webcron_exec', 86400);
		if(!$last_webcron_exec || time()>$last_webcron_exec+3600) {
			App::executeHooks('webcron');
			Cache::set('webcron_exec', time());
		}
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

	/**
	 * Writes a HTTP status header.
	 * 
	 * @access public
	 * @static
	 * @param int $code The HTTP status code
	 * @param int $message (optional) The error message within the HTTP header
	 * @return bool `true` on success, `false` if headers could not be sent or the `$code` was invalid
	 */
	public static function setHttpStatus($code, $message=null) {
		if(headers_sent()) return false;
		$code = (int) $code;
		if($code<100 || $code>999) return false;
		if(!$message) {
			$http_status_codes = array(
				100 => 'Continue',
				101 => 'Switching Protocols',
				102 => 'Processing',
				200 => 'OK',
				201 => 'Created',
				202 => 'Accepted',
				203 => 'Non-Authoritative Information',
				204 => 'No Content',
				205 => 'Reset Content',
				206 => 'Partial Content',
				207 => 'Multi-Status',
				300 => 'Multiple Choices',
				301 => 'Moved Permanently',
				302 => 'Found',
				303 => 'See Other',
				304 => 'Not Modified',
				305 => 'Use Proxy',
				306 => '(Unused)',
				307 => 'Temporary Redirect',
				308 => 'Permanent Redirect',
				400 => 'Bad Request',
				401 => 'Unauthorized',
				402 => 'Payment Required',
				403 => 'Forbidden',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				406 => 'Not Acceptable',
				407 => 'Proxy Authentication Required',
				408 => 'Request Timeout',
				409 => 'Conflict',
				410 => 'Gone',
				411 => 'Length Required',
				412 => 'Precondition Failed',
				413 => 'Request Entity Too Large',
				414 => 'Request-URI Too Long',
				415 => 'Unsupported Media Type',
				416 => 'Requested Range Not Satisfiable',
				417 => 'Expectation Failed',
				418 => 'I\'m a teapot',
				419 => 'Authentication Timeout',
				420 => 'Enhance Your Calm',
				422 => 'Unprocessable Entity',
				423 => 'Locked',
				424 => 'Failed Dependency',
				424 => 'Method Failure',
				425 => 'Unordered Collection',
				426 => 'Upgrade Required',
				428 => 'Precondition Required',
				429 => 'Too Many Requests',
				431 => 'Request Header Fields Too Large',
				444 => 'No Response',
				449 => 'Retry With',
				450 => 'Blocked by Windows Parental Controls',
				451 => 'Unavailable For Legal Reasons',
				494 => 'Request Header Too Large',
				495 => 'Cert Error',
				496 => 'No Cert',
				497 => 'HTTP to HTTPS',
				499 => 'Client Closed Request',
				500 => 'Internal Server Error',
				501 => 'Not Implemented',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Timeout',
				505 => 'HTTP Version Not Supported',
				506 => 'Variant Also Negotiates',
				507 => 'Insufficient Storage',
				508 => 'Loop Detected',
				509 => 'Bandwidth Limit Exceeded',
				510 => 'Not Extended',
				511 => 'Network Authentication Required',
				598 => 'Network read timeout error',
				599 => 'Network connect timeout error'
			);
			if(isset($http_status_codes[$code])) $message = $http_status_codes[$code];
			else $message = 'Unknown Error';
		}
		header('HTTP/1.1 '.$code.' '.$message);
		return true;
	}

	/**
	 * Returns an HTML enrichted text, supporting paragraphs and URL auto detection.
	 * 
	 * @access public
	 * @static
	 * @param string $text The text input
	 * @return string
	 */
	public static function getRichHtml($text) {
		$html = htmlspecialchars(trim($text));
		$html = preg_replace('@(\r\n|\n|\r)@', "\n", $html);
		$html = preg_replace('@\n\s*\n+@', "\n\n", $html);
		$html = preg_replace('@\n([ ]{4,})@', "\n".'<span class="text-indention" style="padding-left: 2em;"></span>', $html);
		$html = nl2br($html, false);
		$html = preg_replace(
			'@(\s|^)(www\..+?)(<|\s|$)@im',
			'$1http://$2$3',
			$html
		);
		$html = preg_replace_callback(
			'@(\s|^)(https?://)(.+?)(<|\s|$)@im',
			function($matches){
				$display_url = $matches[3];
				if(mb_strlen($display_url)>40) {
					$display_url = mb_substr($display_url, 0, 18).'…'.mb_substr($display_url, -18);
				}
				return $matches[1].'<a href="'.$matches[2].$matches[3].'" class="auto-linked external" rel="external nofollow" target="_blank">'.$display_url.'</a>'.$matches[4];
			},
			$html
		);
		return $html;
	}

}
