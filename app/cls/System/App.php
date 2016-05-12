<?php

class App {

	static $start_time;
	static $app_dir = './';
	static $path = null;
	static $languages = array();
	static $lang = null;
	static $session = null;
	static $query = array();
	static $title = '';
	static $title_skip_suffix = false;
	static $protocol = 'http://';
	static $host = '';
	static $uriprefix = '';
	static $preload = array();
	static $hooks = array();
	static $cls_files = array();
	static $sandboxed = false;
	static $custom_tags = array();
	static $js_files = array();

	public static function setAppDir($dir) {
		self::$app_dir = rtrim($dir,'/').'/';
	}

	public static function getAppDir() {
		return self::$app_dir;
	}

	// Cleanup function, should be called at least once a day
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
		foreach(glob('cache/*') as $file) {
			unlink($file);
		}
		// Delete old temp files
		foreach(glob(self::getTempDir().'*') as $file) {
			if(filemtime($file)<time()-86400) {
				unlink($file);
			}
		}
	}

	public static function preload($preload) {
		self::$preload = $preload;
	}

	// Autoloader registration
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

	// Autoloader - has to be used explicitly
	public static function autoload($cls) {
		if(isset(self::$cls_files[$cls])) {
			require_once(self::$cls_files[$cls]);
		}elseif(count(spl_autoload_functions())<=1) {
			Error::fatal("Class could not be found: $cls");
		}
	}

	// Main Application initialization
	public static function init() {
		date_default_timezone_set(Config::get('env','timezone'));
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

	// CDN request handler
	protected static function handleCdnRequest() {
		echo 'CDN REQUEST'; // TODO
	}

	// Fill main menu
	private static function fillMenu() {
		self::addHook('menu',function(){
			$html = '';
			$current = self::getSeofreeTnt();
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

	// Resolve a URI relative to config [env] baseuri
	public static function resolver($uri) {
		$uri = preg_replace('/\?.*$/','',$uri);
		$base = rtrim(Config::get('env','baseuri'),'/');
		return trim(substr($uri,strlen($base)),'/');
	}

	public static function addCustomHtmlTag(CustomHtmlTag $tag) {
		self::$custom_tags[] = $tag;
	}

	// Main render method, loads propper site template
	public static function render($uri=null,$return=false) {
		self::$start_time = microtime(true);
		self::$path = self::getSeofreeTnt();
		$pathok = preg_match('/^[A-Za-z0-9\-_\/\.]+$/',self::$path) && !strstr(self::$path,'..');
		$parts = explode('/',self::$path);
		if($pathok && $parts) self::$query = $parts;
		$template = $parts[0];
		$doc = '';
		for($i=0; $i<count($parts); $i++) {
			$part = $parts[$i];
			if(file_exists("pages/$part.php")) {
				$doc = $parts[$i];
				break;
			}
		}
		$contentfile = "pages/$doc.php";
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
			require_once('pages/+start.php');
		}elseif(preg_match('/\.(jpe?g|gif|png)$/i',self::$path)) {
			require_once('pages/+404+image.php');
		}else{
			require_once('pages/+404.php');
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
		// End buffered output
		$html = self::renderReplacements(ob_get_clean());
		// Debug information
		if(Config::get('debug')) {
			$html = self::renderDebugInformation($html);
		}
		// Final HTML output
		if($return) return $html;
		else echo $html;
	}

	protected static function renderReplacements($html) {
		// Insert title and description
		if(self::$title) {
			$html = str_replace('[[[TITLE]]]',self::getTitle(),$html);
			$canonical = strpos(self::getSeofreeTnt(), strtolower(self::$title))===false ? self::getLink(self::getSeofreeTnt()) : self::getLink(self::getSeofreeTnt(), self::$title);
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

	// get CDN URL if available
	public static function getCdnUrl($url) {
		$cdn_host = Config::get('env', 'cdn_host');
		if(!$cdn_host) return $url;
		return '//'.$cdn_host.'/'.strtr(rtrim(base64_encode($url),'='),'+/','-_');
	}

	// Render CDN replacements
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

	protected static function replaceTags($html, $tag_regex, $replace_callback) {
		return preg_replace_callback("@$tag_regex@", function($matches) use ($replace_callback) {
			return call_user_func($replace_callback, $matches);
		}, $html);
	}

	protected static function replaceUrisInHtmlToCdnVersion($html, $tag, $attr, $regex_ends, $replace_callback) {
		return preg_replace_callback("@<$tag (.*?)$attr=([\"'])([^\"']+)[\"']([^>]*)>@", function($matches) use ($tag, $attr, $regex_ends, $replace_callback) {
			if($regex_ends && !preg_match('@'.$regex_ends.'($|\?)@i', $matches[3])) {
				return $matches[0];
			}
			if(strstr($matches[3],'//') && !strstr($matches[3],'//'.Config::get('env','host').'/')) return $matches[0];
			$html = '<'.$tag.' '.$matches[1].$attr.'='.$matches[2].call_user_func($replace_callback, $matches[3]).$matches[2].$matches[4].'>';
			if(Config::get('debug')) return '<!--CDN-->'.$html.'<!--/CDN-->';
			return $html;
		}, $html);
	}

	protected static function renderDebugInformation($html) {
		$sec = round(microtime(true)-self::$start_time,3).'000';
		$dot = strpos($sec,'.');
		$sec = '<b>'.substr($sec,0,$dot).'.'.substr($sec,$dot+1,1).'</b>'.substr($sec,$dot+2,2);
		$html = str_replace('</body>','<div id="debug-info" style="position: fixed; left: 0; top: 0; background: #000; color: #fff; padding: 0.3em 1em 0.6em 0.3em; opacity: 0.4; white-space: nowrap; max-height: 50%; max-width: 50%; overflow: auto;"><span style="font-size: 0.8em;">Processing time</span><br>'.$sec.' s</div>'."\n".'</body>',$html);
		return $html;
	}

	// Ajax request handling, renders out JSON or single string
	public static function renderajax($uri=null,$return=false) {
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

	public static function seostr($str) {
		$str = str_replace(array('Ä','Ö','Ü','ä','ö','ü','ß'),array('Ae','Oe','Ue','ae','oe','ue','sz'),$str);
		$str = strtr(utf8_decode($str),utf8_decode('ŠŒŽšœžŸ¥µÀÁÂÃÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕØÙÚÛÝàáâãåæçèéêëìíîïðñòóôõøùúûýÿ'),'SOZsozYYuAAAAAACEEEEIIIIDNOOOOOUUUYaaaaaaceeeeiiiionooooouuuyy');
		$str = trim(preg_replace('/\W+/','-',$str),'-');
		return preg_replace('/[^A-Za-z0-9_-]/','',$str);
	}

	public static function getSeofreeTnt() {
		$tnt = self::resolver($_SERVER["REQUEST_URI"]);
		return preg_replace('@/_/[^/]+/?$@','',$tnt);
	}

	public static function link($tnt,$seostr=null,$return=false) {
		$link = rtrim($tnt,'/');
		if($seostr) $link.= '/_/'.self::seostr($seostr);
		$link = self::$uriprefix.ltrim($link,'/');
		if($return) return $link;
		echo $link;
	}

	public static function getLink($tnt=-1,$seostr=null) {
		if($tnt===-1 || !$tnt || $tnt===true) $tnt = self::getPage();
		return self::link($tnt,$seostr,true);
	}

	public static function switchLangLink($newlang) {
		if(self::getLang()===$newlang) {
			$uri = self::getPage().'/';
		}else{
			$uri = self::getSeofreeTnt($uri).'/';
		}
		$link = self::$protocol.$newlang.substr($_SERVER['HTTP_HOST'],strpos($_SERVER['HTTP_HOST'],'.')).'/'.ltrim($uri,'/');
		echo $link;
	}

	public static function setTitle($str, $skip_suffix=false) {
		self::$title = trim($str);
		self::$title_skip_suffix = $skip_suffix;
	}

	public static function getTitle() {
		return self::$title.(self::$title_skip_suffix ? '' : ' '.Config::get('htmlhead','titlesuffix'));
	}

	public static function widget($name) {
		include(Config::get('env','widgets_dir',true)."/$name.php");
	}

	public static function getPage($part=-1) {
		$tnt = self::getSeofreeTnt();
		if($part<0) return $tnt;
		$tnt = explode('/',$tnt);
		return isset($tnt[$part]) ? $tnt[$part] : null;
	}

	public static function getUri($get=array()) {
		$uri = self::getPage();
		if($get) {
			if(is_array($get)) $uri.= '?'.http_build_query($get);
			else $uri.= '?'.$get;
		}
		return $uri;
	}

	public static function getLang() {
		return self::$lang;
	}

	public static function getLanguages() {
		if(!self::$languages) {
			self::$languages = glob('lang/*.csv');
			foreach(self::$languages as $key=>$lang) {
				self::$languages[$key] = substr($lang,5,2);
			}
		}
		return self::$languages;
	}

	public static function getSession() {
		if(!self::$session) return null;
		return self::$session;
	}

	public static function getSid() {
		if(!self::$session) return null;
		return self::$session->getSid();
	}

	public static function getUid() {
		if(!self::$session) return null;
		return self::$session->get('uid');
	}

	public static function getUrl($urlencode=false) {
		$url = $_SERVER["REQUEST_URI"];
		if($pos = strpos($url,'?')) $url = substr($url,0,$pos-1);
		return $urlencode ? urlencode($url) : $url;
	}

	public static function clear() {
		ob_end_clean();
	}

	public static function halt() {
		die();
	}

	public static function redirect($url='/',$fullurl=false) {
		self::clear();
		if(!$fullurl) {
			$url = App::getLink($url);
		}
		header("Location: $url");
		self::halt();
	}

	public static function refresh() {
		self::clear();
		header("Location: ".self::getUrl());
		self::halt();
	}

	public static function linkFile($path, $return=false) {
		$link = Config::get('env','baseuri').trim($path,'/');
		if($return) return $link;
		echo $link;
	}

	public static function linkVersionedFile($path, $return=false) {
		$link = self::linkFile($path,true).'?t='.filemtime($path);
		if($return) return $link;
		echo $link;
	}

	public static function getSkinPath() {
		return 'skins/'.Config::get('env','skin').'/';
	}

	public static function getTempDir() {
		$dir = realpath(Config::get('env','tmp_dir'));
		return rtrim($dir,'/').'/';
	}

	public static function getUserUploadDir() {
		$dir = Config::get('env','user_upload_dir');
		return rtrim($dir,'/').'/';
	}

	public static function createTempFile($prefix) {
		$tempfile = tempnam(self::getTempDir(), $prefix.'_');
		chmod($tempfile, 0777);
		return $tempfile;
	}

	public static function createUserUploadFile($suffix) {
		$path = self::getUserUploadDir();
		do {
			$subdir = mt_rand(10,99);
			$file = $path.$subdir.'/'.$subdir.mt_rand(10,99).mt_rand(1000,9999).mt_rand(1000,9999).mt_rand(1000,9999).$suffix;
		} while(file_exists($file));
		if(!is_dir($path.$subdir)) {
			mkdir($path.$subdir);
			chmod($path.$subdir, 0777);
		}
		touch($file);
		chmod($file, 0777);
		return $file;
	}

	public static function processLinkTrackerAction() {
		$id = self::getPage(1);
		$tracker = LinkTracker::action(self::getPage(1));
	}

	public static function executeHooks($id, $param=null) {
		if(!isset(self::$hooks[$id])) return;
		$result = '';
		foreach(self::$hooks[$id] as $function) {
			$result.= $function($param);
		}
		return $result;
	}

	public static function addHook($id, $function) {
		if(!isset(self::$hooks[$id])) self::$hooks[$id] = array();
		self::$hooks[$id][] = $function;
	}

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

	public static function isSandboxed() {
		return self::$sandboxed;
	}

	public static function setSandboxed($sandboxed=true) {
		self::$sandboxed = (bool) $sandboxed;
	}

	public static function cron($period) {
		if(!$period) return;
		self::executeHooks('cron', $period);
	}

	public static function adminNotification($msg, $subject=null) {
		if(!$subject) $subject = 'Admin Notification';
		mail(Config::get('email', 'admin_notify_addr'), $subject, $msg, "From: ".Config::get('email', 'admin_notify_addr')."\nContent-Type: text/plain; charset=utf-8");
	}

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
