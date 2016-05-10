<?php

abstract class AbstractDatabaseConnector {

	protected static $instance = null;

	public static function getInstance() {
		$class = get_called_class();
		return self::$instance ? self::$instance : new $class;
	}

	protected
		$connection = false,
		$transaction = false,
		$host,
		$database = null,
		$username,
		$password,
		$port,
		$logquery,
		$lastQuery,
		$lastRowOffset;

	public function __construct() {
		if(self::$instance===null) self::$instance = $this;
	}

	final public function isConnected() {
		return $this->connection===false ? false : true;
	}

	abstract protected function error();
	abstract public function connect($host,$username,$password,$database=null,$port=-1);
	abstract public function query($query);
	abstract public function getId($query);
	abstract public function importSqlFile($filename);
	abstract public function exportSqlFile($filename);
	abstract public function escape($str);
	abstract public function startTransaction();
	final public function begin() {return $this->startTransaction();}
	abstract public function rollback();
	abstract public function commit();

	final public function getRows($query, $base_key=null, $single_value=null) {
		if(!is_array($query)) $query = $this->query($query);
		if(!$base_key) return $query;
		$rows = array();
		foreach($query as $row) {
			$rows[$row[$base_key]] = $single_value ? $row[$single_value] : $row;
		}
		return $rows;
	}

	final public function getRow($query=null,$rowOffset=0) {
		if($query===null) {
			$query = $this->lastQuery;
			if($rowOffset===0) {
				if($this->lastRowOffset===null) $this->lastRowOffset = 0;
				else $this->lastRowOffset++;
				$rowOffset = $this->lastRowOffset;
			}
		}elseif(!is_array($query)) $query = $this->query($query);
		return isset($query[$rowOffset]) ? $query[$rowOffset] : array();
	}

	final public function getCell($query,$colOffset=0,$rowOffset=0) {
		if(!is_array($query)) $query = $this->query($query);
		if(!isset($query[$rowOffset])) return null;
		$cells = array_values($query[$rowOffset]);
		return isset($cells[$colOffset]) ? $cells[$colOffset] : null;
	}

	final public function single($query,$colOffset=0,$rowOffset=0) {
		return $this->getCell($query,$colOffset,$rowOffset);
	}

	final public function getHeaders($query) {
		if(!is_array($query)) $query = $this->query($query);
		return isset($query[0]) ? array_keys($query[0]) : null;
	}

	final public function countRows($query) {
		if(!is_array($query)) $query = $this->query($query);
		return count($query);
	}

	final public function countCols($query) {
		if(!is_array($query)) $query = $this->query($query);
		return isset($query[0]) ? count($query[0]) : null;
	}

	final public function getTransactionStatus() {
		return $this->transaction;
	}

	public function prepare($query/* [, $field[, ...]] */) {
		$args = func_get_args();
		array_shift($args);
		foreach($args as $arg) {
			$pos = false;
			$shift = 0;
			$enclosure = '';
			$pos_var = strpos($query,'@VAR');
			$pos_value = strpos($query,'@VAL');
			if($pos_var===false) {
				$pos = $pos_value;
				$shift = 4;
				$enclosure = "'";
			}elseif($pos_value===false) {
				$pos = $pos_var;
				$shift = 4;
				$enclosure = "`";
			}else{
				if($pos_var<$pos_value) {
					$pos = $pos_var;
					$shift = 4;
					$enclosure = "`";
				}else{
					$pos = $pos_value;
					$shift = 4;
					$enclosure = "'";
				}
			}
			if($pos===false) break;
			$query = substr($query,0,$pos).($arg===null ? 'NULL' : $enclosure.$this->escape($arg).$enclosure).substr($query,$pos+$shift);
		}
		return $query;
	}

	public function __toString() {
		return '[AbstractDb Class]';
	}

}
