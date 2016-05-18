<?php

/**
 * Database Collection.
 *
 * Extending classes **must** implement the following `protected` method:
 * <code>
 * protected function getRecordClassName() {
 * 	return 'MyCollectionClass'; // The class name of the objects contained in this colletion
 * }
 * </code>
 *
 * Extending classes **should** implement the following `protected` methods:
 * <code>
 * protected function getSelectFields() {
 * 	return '*'; 
 * }
 * protected function getSelectWhere() {
 * 	return '1'; 
 * }
 * </code>
 */
abstract class AbstractDbCollection extends AbstractDbEntity {

	protected $rows = array();

	abstract protected function getRecordClassName();

	protected function getSelectFields() {
		return '*'; // All columns
	}

	protected function getSelectWhere() {
		return '1'; // '1' means no specific WHERE condition
	}

	/**
	 * Loads the collection with entities from the database.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function loadFromDb() {
		if($this->loaded) return true;
		$this->rows = self::$db->getRows('SELECT '.$this->getSelectFields().' FROM `'.$this->getTable().'` WHERE '.$this->getSelectWhere(), $this->getPrimaryKey());
		return $this->rows!==false;
	}

	/**
	 * Returns the `array` of the collection objects.
	 * 
	 * @access public
	 * @return array
	 */
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

	/**
	 * Returns the underlying rows from the SQL query result.
	 * 
	 * @access public
	 * @return array
	 */
	public function getRows() {
		$this->loadFromDb();
		return $this->rows;
	}

	/**
	 * Returns the number of collection entities.
	 * 
	 * @access public
	 * @param bool $fast (optional) If `true`, an SQL query is performed instead of loading all collection objects and counting them (default: true)
	 * @return int
	 */
	public function count($fast=true) {
		if($fast && !$this->loaded) {
			return self::$db->single("SELECT COUNT(*) FROM `".$this->getTable()."` WHERE ".$this->getSelectWhere());
		}else{
			$this->loadFromDb();
			return $this->rows ? count($this->rows) : 0;
		}
	}

	/**
	 * Returns one object of the collection with the specified `$id`.
	 * 
	 * @access public
	 * @param string $id
	 * @return object
	 */
	public function getRecord($id) {
		if(!$this->loadFromDb() || !$this->rows) return null;
		$record_class_name = $this->getRecordClassName();
		$obj = new $record_class_name;
		$obj->importDbFields($this->rows[$id]);
		return $obj;
	}

	/**
	 * Saves all collection objects to the database.
	 *
	 * ***TODO:*** This method needs to be implemented and has currently no effect.
	 * 
	 * @access public
	 * @param bool $new_if_no_id (optional) If `true`, all objects with no ID assigned will create a new database entity (default: true)
	 * @return bool `true` on success, `false` on error
	 */
	public function saveToDb($new_if_no_id=true) {
		Error::warning('saveToDb is not implemented in AbstractDbCollection.');
		return false;
	}

}
