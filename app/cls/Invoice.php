<?php

class Invoice extends AbstractDbRecord {

	protected function getTable() {
		return 'invoices';
	}

	protected function getPrimaryKey() {
		return 'number';
	}

}
