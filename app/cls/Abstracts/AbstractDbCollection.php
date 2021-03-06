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
abstract class AbstractDbCollection extends AbstractDbEntity implements Iterator {

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
	 * Returns one object of the collection at `$position`.
	 * 
	 * @access public
	 * @param int $position
	 * @return object
	 */
	public function getNthRecord($position) {
		if(!$this->loadFromDb() || !$this->rows) return null;
		$record_class_name = $this->getRecordClassName();
		$obj = new $record_class_name;
		$row = @array_slice($this->rows, $position, 1);
		if(!$row) return null;
		$row = array_shift($row);
		$obj->importDbFields($row);
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

	// Iteration functionality

	private $_position = 0;

	/**
	 * @internal
	 */
	function rewind() {
		$this->_position = 0;
	}

	/**
	 * @internal
	 */
	function current() {
		return $this->getNthRecord($this->_position);
	}

	/**
	 * @internal
	 */
	function key() {
		return $this->_position;
	}

	/**
	 * @internal
	 */
	function next() {
		++$this->_position;
	}

	/**
	 * @internal
	 */
	function valid() {
		if(!$this->loadFromDb() || !$this->rows) return false;
		return ($this->_position >= 0) && ($this->_position < count($this->rows));
	}

	/**
	 * Filters the collection by given fields and values
	 * 
	 * This method modifies the dataset of the existing `AbstractDbCollection` unless `$return_copy` is set to `true`.
	 * 
	 * Example:
	 * <code>
	 * $food = new Food(); // extends AbstractDbCollection
	 * $food->filter(['type' => 'vegetable', 'color' => 'red']);
	 * </code>
	 * 
	 * @access public
	 * @param array $filter The filter to apply
	 * @param bool $return_copy If set to `true` returns a copy of the current filtered `AbstractDbCollection` instead of modifying the original
	 * @return self The filtered collection
	 */
	public function filter($filter, $return_copy=false) {
		$this->loadFromDb();
		$filtered_rows = [];
		foreach($filter as $filter_field=>$filter_value) {
			foreach($this->rows as $row) {
				if(!isset($row[$filter_field]) || $row[$filter_field]!=$filter_value) continue;
				$filtered_rows[] = $row;
			}
		}
		if($return_copy) {
			$obj = clone $this;
			$obj->rows = $filtered_rows;
			return $obj;
		}else{
			$this->rows = $filtered_rows;
			return $this;
		}
	}
	
}
