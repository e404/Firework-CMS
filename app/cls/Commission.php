<?php

class Commission extends AbstractDbRecord {

	protected function getTable() {
		return 'commissions';
	}

	protected function getPrimaryKey() {
		return 'id';
	}

}
