<?php

require_once('app/inc/global.php');

if(Config::get('env', 'published')) {
	$forbidden = false;
}else{
	$forbidden = true;
	$ip = App::getSession()->getRemoteIp();
	$sid = App::getSid();
	if($allow = Config::get('env', 'allow')) {
		if(!is_array($allow)) $allow = array($allow);
		foreach($allow as $value) {
			if(substr($value,0,1)==='[') {
				// HTTP request header value
				list($key, $value) = explode(':',str_replace(array('[',']'),'',$value),2);
				$key = trim($key);
				$value = trim($value);
				if((isset($_SERVER[$key]) && $_SERVER[$key]===$value) || (isset($_COOKIE[$key]) && $_COOKIE[$key]===$value)) {
					$forbidden = false;
					break;
				}
			}elseif($ip===$value || $sid===$value) {
				// IP or Session ID
				$forbidden = false;
			}elseif(substr($value,0,1)==='/' && isset($_SERVER['REQUEST_URI'])) {
				// URL
				if(substr($value,-1)==='*' && substr($value,0,-1)===substr($_SERVER['REQUEST_URI'],0,strlen($value)-1)) {
					// URL with trailing * wildcard
					$forbidden = false;
				}elseif($_SERVER['REQUEST_URI']===$value) {
					// Exact URL
					$forbidden = false;
				}
			}
		}
	}
}

if($forbidden) {
	include(App::getAppDir().'inc/public/403.php');
	die();
}elseif(isset($_SERVER['REDIRECT_IS_AJAX_REQUEST'])) {
	header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
	header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Cache-Control: post-check=0, pre-check=0', false);
	header('Pragma: no-cache');
	App::renderajax();
}elseif(isset($_SERVER['REDIRECT_IS_LINK_REQUEST'])) {
	LinkTracker::action();
}else{
	App::render();
}
