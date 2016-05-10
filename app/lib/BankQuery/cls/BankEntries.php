<?php

class BankEntries {

	protected $entries = [];

	public function add($amount, $date, $valuedate=null, $text=null, $source_accountnumber=null, $source_banknumber=null) {
		$is_iban = false;
		if($source_accountnumber) {
			$source_accountnumber = preg_replace('/[^A-Z0-9]+/s','',strtoupper($source_accountnumber));
			$is_iban = !!preg_match('/^[A-Z]{2}[0-9]{13,32}/',$source_accountnumber);
		}
		if($source_banknumber) {
			$source_banknumber =  preg_replace('/[^A-Z0-9]+/s','',strtoupper($source_banknumber));
		}
		$this->entries[] = [
			'amount' => $this->parseAmount($amount),
			'date' => $this->parseDate($date),
			'valuedate' => $this->parseDate($valuedate),
			'text' => $text,
			'source' => [
				'is_iban' => $is_iban,
				'accountnumber' => $source_accountnumber,
				'banknumber' => $source_banknumber
			]
		];
	}

	protected function parseAmount($amount) {
		if(is_string($amount)) {
			$amount = preg_replace('/[^0-9,\.\-]+/', '', $amount);
			$amount = strrev($amount);
			$amount = preg_split('/[\.,]/', $amount, 2);
			if(count($amount)<2) {
				$amount = $amount[0];
			}else{
				$amount = $amount[0].'X'.$amount[1];
			}
			$amount = strrev($amount);
			$negative = strstr($amount, '-');
			$amount = str_replace('X','.',preg_replace('/[^0-9X]+/', '', $amount));
			if($negative) $amount = '-'.$amount;
		}
		return (double) $amount;
	}

	protected function parseDate($date) {
		if(!$date) return null;
		$time = strtotime($date);
		if(!$time) return null;
		return date('Y-m-d', $time);
	}

	public function getAll() {
		return $this->entries;
	}

}
