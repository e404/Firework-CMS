<?php

/**
 * Front-end notification system.
 *
 * The notification will be loaded and displayed automatically in the website front-end.
 */
class Notify extends NISystem {

	/** @internl */
	public static function ajax() {
		return array(
			'sticky' => self::isSticky(),
			'msgs' => self::readMessages()
		);
	}

	/**
	 * Triggers a notification.
	 * 
	 * @access public
	 * @static
	 * @param string $msg
	 * @return void
	 */
	public static function msg($msg) {
		$session = App::getSession();
		$notify = $session->get('notify');
		if(!$msg) return null;
		if(!$notify || !is_array($notify)) $notify = array();
		$msg = App::getLang()->translateHtml($msg);
		if(!in_array($msg, $notify)) $notify[] = $msg;
		$session->set('notify',$notify);
	}

	/**
	 * If called, the notification will be marked as 'sticky'.
	 *
	 * A user action has to be taken to dismiss the notification.
	 * 
	 * @access public
	 * @static
	 * @return void
	 */
	public static function setSticky() {
		App::getSession()->set('notify_sticky');
	}

	/**
	 * Checks if a notification is marked as 'sticky'.
	 * 
	 * @access public
	 * @static
	 * @return bool `true` if sticky, `false` if not
	 */
	public static function isSticky() {
		return (bool) App::getSession()->get('notify_sticky');
	}

	/** @internal */
	public static function readMessages() {
		$session = App::getSession();
		$notify = $session->get('notify');
		if(!$notify) return null;
		$session->remove('notify');
		$session->remove('notify_sticky');
		return $notify;
	}

	/**
	 * Checks if there are notifications to be displayed.
	 * 
	 * @access public
	 * @static
	 * @return bool `true` if notifications are present, `false` if not
	 */
	public static function hasMessages() {
		return (bool) App::getSession()->get('notify');
	}

}
