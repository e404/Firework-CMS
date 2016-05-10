<?php

class PaymentTicket extends AbstractDbRecord {

	protected function getTable() {
		return 'paymenttickets';
	}

	protected function getPrimaryKey() {
		return 'tid';
	}

	protected function generateId() {
		return Random::generateEasyCode(6);
	}

}
