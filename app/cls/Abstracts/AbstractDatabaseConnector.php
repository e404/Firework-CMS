<?php

/**
 * Database Connector.
 */
abstract class AbstractDatabaseConnector {

	protected static $instance = null;

	/**
	 * Returns the current database connector instance or creates a new one.
	 * 
	 * @access public
	 * @static
	 */
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

	/** @internal */
	public function __construct() {
		if(self::$instance===null) self::$instance = $this;
	}

	/**
	 * Check if the database connector has already connected.
	 * 
	 * @access public
	 * @final
	 * @return bool `true` if connected, `false` otherwise
	 */
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
	abstract public function inTransaction();

	/**
	 * Alias for `startTransaction()`.
	 * 
	 * @access public
	 * @final
	 * @return bool
	 * @see self::startTransaction()
	 */
	final public function begin() {return $this->startTransaction();}

	abstract public function rollback();
	abstract public function commit();

	/**
	 * Returns the resulting rows of a query.
	 *
	 * @access public
	 * @final
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @param string $base_key (optional) If set, the rows will be based on the specified `$base_key` (default: null)
	 * @param mixed $single_value (optional) If set, the rows will only contain one value instead of an array (default: null)
	 * @return array
	 */
	final public function getRows($query, $base_key=null, $single_value=null) {
		if(!is_array($query)) $query = $this->query($query);
		if(!$base_key) return $query;
		$rows = array();
		foreach($query as $row) {
			$rows[$row[$base_key]] = $single_value ? $row[$single_value] : $row;
		}
		return $rows;
	}

	/**
	 * Returns one row of a query.
	 * 
	 * @access public
	 * @final
	 * @param mixed $query (optional) If omitted, the last query result will be used; otherwise the resulting query `array` or an SQL query `string` (default: null)
	 * @param int $rowOffset The row number which must be used, starting with `0` (default: 0)
	 * @return array
	 */
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

	/**
	 * Returns one single value of a query.
	 * 
	 * @access public
	 * @final
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @param int $colOffset (optional) The column number, starting with `0` (default: 0)
	 * @param int $rowOffset (optional) The row number, starting with `0` (default: 0)
	 * @return string
	 */
	final public function getCell($query,$colOffset=0,$rowOffset=0) {
		if(!is_array($query)) $query = $this->query($query);
		if(!isset($query[$rowOffset])) return null;
		$cells = array_values($query[$rowOffset]);
		return isset($cells[$colOffset]) ? $cells[$colOffset] : null;
	}

	/**
	 * Alias for `getCell()`.
	 * 
	 * @access public
	 * @final
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @param int $colOffset (optional) The column number, starting with `0` (default: 0)
	 * @param int $rowOffset (optional) The row number, starting with `0` (default: 0)
	 * @return string
	 * @see self::getCell()
	 */
	final public function single($query,$colOffset=0,$rowOffset=0) {
		return $this->getCell($query,$colOffset,$rowOffset);
	}

	/**
	 * Transforms the resulting rows into object instancing `AbstractDbRecord`.
	 * 
	 * @access public
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @param string $recordClassName Class name instancing `AbstractDbRecord`
	 * @return array An array of `AbstractDbRecord` objects with the primary keys as array keys.
	 * @see self::query()
	 */
	public function queryIntoRecords($query, $recordClassName) {
		if(!class_exists($recordClassName) || !((new $recordClassName) instanceof AbstractDbRecord)) {
			Error::warning('Could not create records using class $recordClassName.');
			return [];
		}
		if(!is_array($query)) $query = $this->query($query);
		if(!$query) return [];
		$result = [];
		foreach($query as $row) {
			$obj = new $recordClassName;
			$obj->importDbFields($row);
			$result[] = $obj;
		}
		return $result;
	}

	/**
	 * Returns the column names of the query result.
	 * 
	 * @access public
	 * @final
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @return array
	 */
	final public function getHeaders($query) {
		if(!is_array($query)) $query = $this->query($query);
		return isset($query[0]) ? array_keys($query[0]) : null;
	}

	/**
	 * The number of rows the query result produced.
	 * 
	 * @access public
	 * @final
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @return int
	 */
	final public function countRows($query) {
		if(!is_array($query)) $query = $this->query($query);
		return count($query);
	}

	/**
	 * The number of columns the query result produced.
	 * 
	 * @access public
	 * @final
	 * @param mixed $query The resulting query `array` or an SQL query `string`
	 * @return int
	 */
	final public function countCols($query) {
		if(!is_array($query)) $query = $this->query($query);
		return isset($query[0]) ? count($query[0]) : null;
	}

	/**
	 * Checks if a transaction has been started using `startTransaction()`.
	 * 
	 * @access public
	 * @final
	 * @return bool `true` if in transaction context, `false` otherwise
	 * @see self::startTransaction()
	 */
	final public function getTransactionStatus() {
		return $this->transaction;
	}

	/**
	 * Prepares an SQL query.
	 *
	 * This method makes queries safe against SQL injection attempts.
	 * 
	 * @access public
	 * @param string $query The SQL query `string`.<br>
	 *  `@VAL` prepares a value string,<br>
	 *  `@VAR` prepares a column name, or some other sort of identifier.
	 * @param string $field The fields that should be replaced in the prepared query.
	 * @return string
	 * @see self::query()
	 *
	 * @example
	 * <code>
	 * // ...
	 * $result = $db->query(
	 * 	$db->prepare('
	 * 		SELECT *
	 * 			FROM table
	 * 			WHERE id=@VAL
	 * 			AND @VAR=@VAL',
	 * 		$id,
	 * 		$search_field,
	 * 		$search_value
	 * 	)
	 * );
	 * </code>
	 */
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

	/** @internal */
	public function __toString() {
		return '[AbstractDb Class]';
	}

}
