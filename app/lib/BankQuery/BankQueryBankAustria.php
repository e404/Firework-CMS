<?php

require_once(__DIR__.'/cls/BankQuery.php');

class BankQueryBankAustria extends BankQuery {

	protected function runLogin($username, $password) {
		$this->browser->navigate('https://online.bankaustria.at/wps/portal/userlogin');
		$this->browser->enter('LoginPortletFormID', $username);
		$this->browser->enter('LoginPortletFormPassword', $password);
		$this->browser->click('Login');
		return true;
	}

	protected function runGetEntries() {
		// TODO
	}

	protected function runStartTransaction($amount, $iban, $bic, $name, $reference) {
		$this->browser->click('EU-Binnenzahlung');
	}

	protected function runLogout() {
		$this->browser->click('Logout');
		return true;
	}

}
