<?php

if(!defined('MYSQLDB_LONG_QUERY_SEC')) define('MYSQLDB_LONG_QUERY_SEC', 1.0);

/**
 * MySQL Database Connector.
 */
class MysqlDb extends AbstractDatabaseConnector {

	protected $last_error = null;
	protected $ignore_long_queries = false;
	protected $cache_queries = false;
	protected $query_cache = array();

	protected function error() {
		$error = mysqli_error($this->connection);
		if($error) {
			$this->last_error = $error;
			trigger_error($error,E_USER_WARNING);
			return true;
		}
		$this->last_error = null;
		return false;
	}

	/**
	 * Decides if long running queries should trigger a warning.
	 * 
	 * @access public
	 * @param bool $ignore If set to false, long queries will trigger a warning (default: true)
	 * @return void
	 */
	public function ignoreLongQueries($ignore=true) {
		$this->ignore_long_queries = (bool) $ignore;
	}

	/**
	 * Enables query caching for performance improvement.
	 * 
	 * Query results get stored and returned if the exact same query (identified via `$query` string) gets called again.
	 * If there is any query other than `SELECT` or `SHOW`, the cache gets purged, unless a transaction has been started with `startTransaction()`.
	 * 
	 * @access public
	 * @param bool $cache If set to true, results will be cached (default: true)
	 * @return void
	 */
	public function enableCache($cache=true) {
		$this->cache_queries = (bool) $cache;
	}

	/**
	 * Connects to the MySQL database.
	 * 
	 * @access public
	 * @final
	 * @param string $host
	 * @param string $username
	 * @param string $password
	 * @param string $database (optional) If omitted, no default database will be used (default: null)
	 * @param float $port (optional) `-1` means default port (default: -1)
	 * @return void
	 */
	final public function connect($host,$username,$password,$database=null,$port=-1) {
		$this->connection = null;
		$this->host = $host;
		$this->database = $database;
		$this->username = $username;
		$this->password = $password;
		$this->port = $port;
	}

	protected function openConnection() {
		if($this->connection) return true;
		if($this->connection = @mysqli_connect(
				$this->host.($this->port===-1?"":":".$this->port),
				$this->username,
				$this->password
			)
		) {
			if(!mysqli_set_charset($this->connection, 'utf8')) {
				trigger_error("MySQL charset utf8 not supported",E_USER_ERROR);
				return false;
			}
			if($this->database===null) {
				return true;
			}elseif(mysqli_select_db($this->connection,$this->database)) {
				return true;
			}else{
				trigger_error("Database could not be selected",E_USER_ERROR);
				return false;
			}
		}else{
			trigger_error("Database connection could not be established: ".mysqli_error(),E_USER_ERROR);
			return false;
		}
	}

	/**
	 * Runs a MySQL query.
	 *
	 * Returns an array on `SELECT` queries, and `true` or `false` on manipulation queries.
	 * 
	 * @access public
	 * @param string $query The SQL query string
	 * @return mixed
	 */
	public function query($query) {
		if(func_num_args()>1) {
			$result = array();
			foreach(func_get_args() as $query) {
				$result[] = $this->query($query);
			}
			return $result;
		}
		$cache_query = ($this->cache_queries && !$this->transaction && preg_match('/^\s*(SELECT|SHOW)\s/si', $query));
		if($cache_query && isset($this->query_cache[$query])) {
			if(Config::get('debug') && Config::get('debug','db_queries')) {
				Error::debug('Cached DB Query: "'.$query."\"\n→ 0 sec");
			}
			return $this->query_cache[$query];
		}
		$this->openConnection();
		$time_start = microtime(true);
		$query_obj = mysqli_query($this->connection,$query);
		$this->error();
		$duration = microtime(true)-$time_start;
		if(Config::get('debug') && Config::get('debug','db_queries')) {
			Error::debug('DB Query: "'.$query."\"\n→ ".round($duration,4).' sec');
		}
		if(!$this->ignore_long_queries && $duration>MYSQLDB_LONG_QUERY_SEC) {
			Error::warning('DB Query took a long time ('.round($duration,4).' sec)');
		}
		if(is_bool($query_obj)) {
			$result = $query_obj;
		}else{
			$result = array();
			while($row = mysqli_fetch_assoc($query_obj)) {
				$result[] = $row;
			}
			$this->lastQuery = $result;
			$this->lastRowOffset = null;
			mysqli_free_result($query_obj);
		}
		if($cache_query) {
			$this->query_cache[$query] = $result;
		}elseif($this->cache_queries) {
			$this->query_cache = array();
		}
		return $result;
	}

	/**
	 * Runs a MySQL multi query.
	 *
	 * Returns a two-dimensional array.
	 * 
	 * @access public
	 * @param string $query The SQL queries string
	 * @return mixed array or `false` on error
	 */
	public function multiQuery($query) {
		$this->query_cache = array();
		$this->openConnection();
		$time_start = microtime(true);
		$query_ok = mysqli_multi_query($this->connection, $query);
		$this->error();
		$duration = microtime(true)-$time_start;
		if(Config::get('debug') && Config::get('debug','db_queries')) {
			Error::debug('DB Multi Query: "'.substr($query,0,64).(strlen($query)>64 ? '"...' : '"')."\n→ ".round($duration,4).' sec');
		}
		if(!$this->ignore_long_queries && $duration>MYSQLDB_LONG_QUERY_SEC) {
			Error::warning('DB Query took a long time ('.round($duration,4).' sec)');
		}
		$this->lastQuery = null;
		$this->lastRowOffset = null;
		if($query_ok) {
			$result = array();
			$count = 0;
			do {
				$result[$count] = array();
				if($sql_result = mysqli_store_result($this->connection)) {
					while($row = mysqli_fetch_assoc($sql_result)) {
						$result[$count][] = $row;
					}
					mysqli_free_result($sql_result);
				}else{
					if(mysqli_errno($this->connection)) {
						$this->error();
						return false;
					}
				}
				$count++;
			}while(mysqli_more_results($this->connection) && mysqli_next_result($this->connection));
			return $result;
		}else{
			$this->error();
			return false;
		}
	}

	/**
	 * Runs a query and returns the generated auto-increment ID if applicable.
	 *
	 * If no ID could be found, `null` will be returned.
	 * 
	 * @access public
	 * @final
	 * @param string $query The SQL query string
	 * @return mixed
	 */
	final public function getId($query) {
		if(!preg_match("/^\s*(INSERT|REPLACE)\s/si",$query)) return null;
		$success = $this->query($query);
		return $success===true ? mysqli_insert_id($this->connection) : null;
	}

	/**
	 * Runs (imports) an SQL file to the database.
	 * 
	 * @access public
	 * @param string $filename
	 * @return bool `true` on success, `false` on error
	 */
	public function importSqlFile($filename) {
		if(!$this->database) {
			Error::warning('No database selected.');
			return null;
		}
		exec("mysql --host=".$this->host." --user=".$this->username." --password=".$this->password." ".$this->database." < ".$filename,$output,$return_var);
		$this->query_cache = array();
		return ($return_var===0);
	}

	/**
	 * Dumps the selected database to an SQL file.
	 * 
	 * @access public
	 * @param string $filename
	 * @return bool `true` on success, `false` on error
	 */
	public function exportSqlFile($filename) {
		if(!$this->database) {
			Error::warning('No database selected.');
			return null;
		}
		exec("mysqldump --host=".$this->host." --user=".$this->username." --password=".$this->password." ".$this->database." > ".$filename,$output,$return_var);
		return ($return_var===0);
	}

	/**
	 * Escapes a string for safe usage within SQL queries.
	 * 
	 * @access public
	 * @param string $str
	 * @return string
	 */
	public function escape($str) {
		$this->openConnection();
		return mysqli_real_escape_string($this->connection,$str);
	}

	/**
	 * Starts a transaction.
	 *
	 * This method will not work with MyISAM databases.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function startTransaction() {
		if($this->inTransaction()) return -1;
		$this->transaction = true;
		return $this->query("START TRANSACTION");
	}

	/**
	 * Undos all queries since the lase `startTransaction` call.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function rollback() {
		$this->transaction = false;
		return $this->query("ROLLBACK");
	}

	/**
	 * Makes all queries since the last `startTransaction` call permanent.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function commit() {
		$this->transaction = false;
		return $this->query("COMMIT");
	}

	/**
	 * Checks if a transaction has been started.
	 * 
	 * @access public
	 * @return bool `true` if a transaction has been started, `false` if not
	 */
	public function inTransaction() {
		return $this->transaction;
	}

	/**
	 * Returns a pre-formatted information of all available tables and columns.
	 * 
	 * @access public
	 * @return string
	 */
	public function info() {
		$info = "";
		foreach($this->query("SHOW TABLES") as $table) {
			$table = array_values($table);
			$table = $table[0];
			$info.= "\n<b>\n+".str_repeat("-",44)."+\n|".str_pad($table,44," ")."|\n+".str_repeat("-",44)."+\n</b>\n";
			$def = $this->query("SHOW COLUMNS FROM `".$table."`");
			foreach($def as $d) {
				$info.= "   +".str_repeat("-",41)."+\n";
				foreach($d as $key=>$val) {
					$info.= "   |".str_pad($key,8," ").": ".str_pad($val,31," ")."|\n";
				}
				$info.= "   +".str_repeat("-",41)."+\n";
			}
		}
		return $info;
	}

	/**
	 * Write-locks a table.
	 * 
	 * @access public
	 * @param string $table
	 * @return bool `true` on success, `false` on error
	 */
	public function lockTable($table) {
		return $this->query("LOCK TABLES `".$this->escape($table)."` WRITE");
	}

	/**
	 * Unlocks all previously locked tables.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function unlockTables() {
		return (bool) @mysqli_query($this->connection,"UNLOCK TABLES");
	}

	/**
	 * Executes a stored procedure.
	 *
	 * @access public
	 * @param string $name The name of the routine
	 * @param mixed $params,... (optional) Procedure parameters
	 * @return mixed `true` on success for empty responses, otherwise an array; `false` on error
	 */
	public function callProcedure($name, $params=null /*[, $params[, ...]]*/) {
		$args = func_get_args();
		array_shift($args);
		array_map([$this, 'escape'], $args);
		$args = $args ? "'".implode("', '", $args)."'" : '';
		return $this->multiQuery('CALL `'.$this->escape($name).'`('.$args.')');
	}

	/**
	 * Returns the error message of the last SQL query.
	 * 
	 * @access public
	 * @return string The error message; `null` if no error was encountered.
	 */
	public function getLastError() {
		return $this->last_error;
	}

}
