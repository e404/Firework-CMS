<?php

class Notify {

	public static function ajax() {
		return array(
			'sticky' => self::isSticky(),
			'msgs' => self::readMessages()
		);
	}

	public static function msg($msg) {
		$session = App::getSession();
		$notify = $session->get('notify');
		if(!$msg) return null;
		if(!$notify || !is_array($notify)) $notify = array();
		$msg = App::getLang()->translateHtml($msg);
		if(!in_array($msg, $notify)) $notify[] = $msg;
		$session->set('notify',$notify);
	}

	public static function setSticky() {
		App::getSession()->set('notify_sticky');
	}

	public static function isSticky() {
		return (bool) App::getSession()->get('notify_sticky');
	}

	public static function readMessages() {
		$session = App::getSession();
		$notify = $session->get('notify');
		if(!$notify) return null;
		$session->remove('notify');
		$session->remove('notify_sticky');
		return $notify;
	}

	public static function hasMessages() {
		return (bool) App::getSession()->get('notify');
	}

}
