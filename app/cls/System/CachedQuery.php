<?php

/**
 * Queries the database and caches the result for later use.
 */
class CachedQuery extends Db {

	use Inject;

	protected $lifetime_sec = null;

	/**
	 * @access public
	 * @param int $lifetime_sec (optional) Defines the cache lifetime in seconds (default: null)
	 * @return void
	 */
	public function __construct($lifetime_sec=null) {
		if($lifetime_sec) $this->setLifetime($lifetime_sec);
		parent::__construct();
	}

	/**
	 * Defines the cache lifetime in seconds.
	 * 
	 * @access public
	 * @param int $lifetime_sec
	 * @return void
	 */
	public function setLifetime($lifetime_sec) {
		$this->lifetime_sec = $lifetime_sec ? (int) $lifetime_sec : null;
	}

	/**
	 * Returns the cache lifetime in seconds.
	 * 
	 * @access public
	 * @return int
	 */
	public function getLifetime() {
		return $this->lifetime_sec;
	}

	/**
	 * Runs a query.
	 * 
	 * @access public
	 * @param mixed $query
	 * @return void
	 */
	public function execute($query) {
		if($this->lifetime_sec && is_string($query)) {
			$file = 'db.'.md5(trim($query)).'.private.phpdata';
			$age = Cache::getAge($file);
			if($age!==false && $age<=$this->lifetime_sec) {
				Error::debug('Reading cached query: '.$file);
				return Cache::readFile($file,true);
			}
			$query = self::$db->query($query);
			Error::debug('Writing cached query: '.$file);
			Cache::writeFile($file,$query,true);
			return $query;
		}else{
			return self::$db->query($query,$strict);
		}
	}

	/**
	 * Returns the internal `Db` object.
	 * 
	 * @access public
	 * @return Db
	 */
	public function getDb() {
		return self::$db;
	}

}
