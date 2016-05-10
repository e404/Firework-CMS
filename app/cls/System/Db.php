<?php

// Database Object
// Implements $this->db to any extending class

class Db extends Instantiable {

	protected static $db = null;

	public function __construct() {
		self::db();
	}

	public static function db() {
		if(!self::$db) self::$db = MysqlDb::getInstance();
		return self::$db;
	}

}
