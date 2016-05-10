<?php

require_once(__DIR__.'/cls/BankQuery.php');

class BankQueryDKB extends BankQuery {

	protected function runLogin($username, $password) {
		$this->browser->navigate('https://banking.dkb.de/-');
		$this->browser->click('here');
		$this->browser->enter('j_username', $username);
		$this->browser->enter('j_password', $password);
		$this->browser->click('Anmelden');
		return true;
	}

	protected function runGetEntries() {
		$entries = new BankEntries();
		$this->browser->click('Kontoumsätze');
		$result = $this->browser->getSourceCode();
		if(!$result || !preg_match_all('@<tr class="mainRow">(.*?)</tr>@s', $result, $trs)) {
			return false;
		}
		foreach($trs[1] as $tr) {
			$newdata = array();
			if(!preg_match_all('@<td[^>]*>(.*?)</td>@s', $tr, $tds)) continue;
			foreach($tds[1] as $td) {
				$td = preg_replace('@<[^>]+>@', "\n", $td);
				$td = preg_replace('@[\t ]{2,}@s',"\n",trim($td));
				$td = preg_replace('@[\t ]+@s',' ',trim($td));
				$td = preg_split('@[\r\n]+@',$td);
				$td = array_map('trim',$td);
				$td = array_filter($td);
				$newdata[] = array_values($td);
			}
			$sourceaccount = strtoupper(preg_replace('@\s+@', '', $newdata[2][0]));
			$sourcebanknumber = strtoupper(preg_replace('@\s+@', '', $newdata[2][1]));
			$amount = (double) str_replace(array('.',','), array('', '.'), $newdata[3][0]);
			$entries->add($amount, $newdata[0][1], $newdata[0][0], $newdata[1], $sourceaccount, $sourcebanknumber);
		}
		return $entries;
	}

	protected function runStartTransaction($amount, $iban, $bic, $name, $reference) {
		$this->browser->click('Überweisung');
		$this->browser->click('Neue Überweisung');
		$this->browser->select('creditorAccountType');
		// TODO: Handle input[type=radio] - Warning: Could have same name. Check for "selected" / deselect all before when selecting new one
		// TODO: Handle select
		$this->browser->enter('creditorName', $name);
		$this->browser->enter('creditorAccountNo', $iban);
		$this->browser->enter('creditorBankcode', $bic);
		$this->browser->click('Betrag wählen'); // TODO: Find form and submit
		$this->browser->enter('amountToTransfer', number_format($amount,2,',',''));
		$this->browser->enter('paymentPurposeLine', $reference); // TODO: This is a textarea !!
		$this->browser->click('TAN eingeben');
		// TODO: TAN handling
	}

	protected function runLogout() {
		$this->browser->click('Abmelden');
		return true;
	}

}
