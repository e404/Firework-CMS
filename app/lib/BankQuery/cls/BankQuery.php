<?php

require_once(__DIR__.'/BankEntries.php');
require_once(__DIR__.'/BankBrowser.php');

abstract class BankQuery {

	protected $browser = null;
	private $loggedin = false;
	private $entries = null;

	public function __construct() {
		$this->browser = new BankBrowser();
	}

	abstract protected function runLogin($username, $password);
	abstract protected function runLogout();
	abstract protected function runGetEntries();
	abstract protected function runStartTransaction($amount, $iban, $bic, $name, $reference);

	public function login($username, $password) {
		$this->loggedin = $this->runLogin($username, $password);
		return $this->loggedin;
	}

	public function logout() {
		if(!$this->loggedin) return false;
		$this->loggedin = false;
		return $this->runLogout();
	}

	public function getEntries() {
		if(!$this->loggedin) return false;
		if($this->entries!==null) return $this->entries;
		$this->entries =& $this->runGetEntries();
		if(!$this->entries || !($this->entries instanceof BankEntries)) return null;
		return $this->entries->getAll();
	}

	public function isPayed($amount, $reference_code='') {
		if(!$this->loggedin) return false;
		$this->getEntries();
		$reference_code = strtoupper($reference_code);
		foreach($this->entries->getAll() as $entry) {
			if($entry['amount']==$amount) {
				if(!$reference_code) return true;
				if(strstr(strtoupper(preg_replace('@\s+@s','',implode('',$entry['text']))), $reference_code)) return true;
			}
		}
		return false;
	}

	public function startTransaction() {
		if(!$this->loggedin) return false;
		return $this->runStartTransaction();
	}

}
