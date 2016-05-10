<?php

class CachedQuery extends Db {

	protected $lifetime_sec = null;

	public function __construct($lifetime_sec=null) {
		if($lifetime_sec) $this->setLifetime($lifetime_sec);
		parent::__construct();
	}

	public function setLifetime($lifetime_sec) {
		$this->lifetime_sec = $lifetime_sec ? (int) $lifetime_sec : null;
	}

	public function getLifetime() {
		return $this->lifetime_sec;
	}

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

	public function getDb() {
		return self::$db;
	}

}
