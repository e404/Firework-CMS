<?php

require_once('lib/add_subscriber.php');

class NewsletterSubscriber {

	protected static function add($list, $email, $additional_fields=array()) {
		if(!$list) {
			Error::warning('No newsletter list ID defined.');
			return false;
		}
		if(!$email) {
			Error::warning('No email address given.');
			return false;
		}
		$result = add_subscriber(Config::get('newsletter', 'blogurl'), Config::get('newsletter', 'apikey'), $list, $email, $additional_fields);
		if($result===2) {
			Error::warning('Newsletter subscriber already exists in list '.$list.': '.$email);
		}elseif(!$result) {
			Error::warning('Could not add newsletter subscriber to list '.$list.': '.$email);
			return false;
		}
		return true;
	}

	public static function addCustomer($email) {
		return self::add(Config::get('newsletter', 'listid_customers'), $email);
	}

	public static function addAffiliate($email, $affid) {
		return self::add(Config::get('newsletter', 'listid_affiliates'), $email, array('affid' => $affid));
	}

	public static function addProspective($email, $firstname) {
		return self::add(Config::get('newsletter', 'listid_prospective'), $email, array('firstname' => $firstname));
	}

}
