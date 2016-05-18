<?php

/**
 * Database Object.
 *
 * Implements `$this->db` to any extending class
 */
class Db extends Instantiable {

	protected static $db = null;

	/**
	 * Initializes the database.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct() {
		self::db();
	}

	/**
	 * Static method for receiving the `Db` object.
	 * 
	 * @access public
	 * @static
	 * @return Db
	 */
	public static function db() {
		if(!self::$db) self::$db = MysqlDb::getInstance();
		return self::$db;
	}

}
