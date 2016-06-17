<?php

require_once('app/inc/global.php');

if(Config::get('env', 'published')) {
	$forbidden = false;
}else{
	$ip = App::getSession()->getRemoteIp();
	$allow = Config::get('env', 'allow');
	if($allow && !is_array($allow)) $allow = array($allow);
	if($allow && (in_array($ip, $allow) || in_array(App::getSid(), $allow))) {
		$forbidden = false;
	}else{
		$forbidden = true;
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
