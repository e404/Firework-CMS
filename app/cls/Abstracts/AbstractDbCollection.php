<?php

abstract class AbstractDbCollection extends AbstractDbEntity {

	protected $rows = array();

	abstract protected function getRecordClassName();

	protected function getSelectFields() {
		return '*';
	}

	protected function getSelectWhere() {
		return '1';
	}

	public function loadFromDb() {
		if($this->loaded) return true;
		$this->rows = self::$db->getRows('SELECT '.$this->getSelectFields().' FROM `'.$this->getTable().'` WHERE '.$this->getSelectWhere(), $this->getPrimaryKey());
		return $this->rows!==false;
	}

	public function getCollection() {
		$this->loadFromDb();
		$record_class_name = $this->getRecordClassName();
		$collection = array();
		foreach($this->rows as $row) {
			$obj = new $record_class_name;
			$obj->importDbFields($row);
			$collection[] = $obj;
		}
		return $collection;
	}

	public function getRows() {
		$this->loadFromDb();
		return $this->rows;
	}

	public function count($fast=true) {
		if($fast && !$this->loaded) {
			return self::$db->single("SELECT COUNT(*) FROM `".$this->getTable()."` WHERE ".$this->getSelectWhere());
		}else{
			$this->loadFromDb();
			return $this->rows ? count($this->rows) : 0;
		}
	}

	public function getRecord($id) {
		if(!$this->loadFromDb() || !$this->rows) return null;
		$record_class_name = $this->getRecordClassName();
		$obj = new $record_class_name;
		$obj->importDbFields($this->rows[$id]);
		return $obj;
	}

	public function saveToDb($new_if_no_id=true) {
		Error::warning('saveToDb is not implemented in AbstractDbCollection.');
		return false;
	}

}
