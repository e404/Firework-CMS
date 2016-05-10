<?php

abstract class AbstractDbEntity extends Db {

	protected $loaded = false;
	protected $changed = false;

	abstract protected function getTable();
	abstract protected function getPrimaryKey();

	public function __construct() {
		parent::__construct();
	}

	abstract public function loadFromDb();

	protected function isReadOnly() {
		return false;
	}

	abstract public function saveToDb($new_if_no_id=true);

	public function save() {
		return $this->saveToDb();
	}

}
