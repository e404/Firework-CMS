<?php

class Affiliate extends AbstractDbRecord {

	protected function getTable() {
		return 'affiliates';
	}

	protected function getPrimaryKey() {
		return 'affid';
	}

	protected function generateId() {
		return Random::generate(6);
	}

	public function isActive() {
		return (bool) $this->getField('active');
	}

	public function activate() {
		NewsletterSubscriber::addAffiliate($this->getField('email'), $this->getField('affid'));
		return self::$db->query(self::$db->prepare("UPDATE @VAR SET `active`=1 WHERE @VAR=@VAL LIMIT 1", $this->getTable(), $this->getPrimaryKey(), $this->getId()));
	}

	public function trackClick() {
		if(!$this->getId()) return false;
		self::$db->query(self::$db->prepare("INSERT INTO `affiliateclicks` SET affid=@VAL, ip=@VAL", $this->getId(), $_SERVER['REMOTE_ADDR']));
		self::$db->query(self::$db->prepare("UPDATE @VAR SET clicks_total=clicks_total+1 WHERE @VAR=@VAL LIMIT 1", $this->getTable(), $this->getPrimaryKey(), $this->getId()));
		return true;
	}

	public function getClicks($days) {
		if(!$this->getId()) return array();
		$time = strtotime(date('Y-m-d -'.$days.' days'));
		return self::$db->query(self::$db->prepare("SELECT * FROM `affiliateclicks` WHERE `affid`=@VAL AND `t`>@VAL", $this->getId(), date('Y-m-d H:i:s', $time)));
	}

	public function getCommissions($days) {
		if(!$this->getId()) return array();
		$time = strtotime(date('Y-m-d -'.$days.' days'));
		return self::$db->query(self::$db->prepare("SELECT * FROM `commissions` WHERE `affid`=@VAL AND `ts`>@VAL", $this->getId(), date('Y-m-d H:i:s', $time)));
	}

	public function getName() {
		if($name = $this->getField('company')) return $name;
		return $this->getField('firstname').' '.$this->getField('lastname');
	}

}
