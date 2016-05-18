<?php

/**
 * Database Entity.
 *
 * Extending classes **must** implement the following `protected` methods:
 * <code>
 * protected function getTable() {
 * 	return 'my_table';
 * }
 * protected function getPrimaryKey() {
 * 	return 'id';
 * }
 * protected function loadFromDb() {
 * 	// ...
 * }
 * </code>
 *
 * Extending classes **should** implement the following `protected` method:
 * <code>
 * protected function isReadOnly() {
 * 	return false;
 * }
 * </code>
 */
abstract class AbstractDbEntity extends Db {

	protected $loaded = false;
	protected $changed = false;

	abstract protected function getTable();
	abstract protected function getPrimaryKey();

	/** @internal */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Loads the entity content from the database.
	 * 
	 * @access public
	 * @abstract
	 * @return void
	 */
	abstract public function loadFromDb();

	protected function isReadOnly() {
		return false;
	}

	/**
	 * Saves the entity to the database.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	abstract public function saveToDb($new_if_no_id=true);

	/**
	 * Alias for `saveToDb()`
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 * @see self::saveToDb()
	 */
	public function save() {
		return $this->saveToDb();
	}

}
