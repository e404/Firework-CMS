<?php

abstract class AbstractDbRecord extends AbstractDbEntity {

	protected $id = null;
	protected $dbfields = array();
	protected $changed_fields = array();

	public function __construct($id=null) {
		parent::__construct();
		if($id!==null) {
			$this->setId($id);
			$this->loadFromDb();
		}
	}

	protected function generateId() {
		return null;
	}

	public function setId($id) {
		$this->id = $id;
	}

	public function getId() {
		return $this->id;
	}

	public function delete() {
		if(!$this->id) return false;
		return (bool) self::$db->query(self::$db->prepare("DELETE FROM @VAR WHERE @VAR=@VAL LIMIT 1", $this->getTable(), $this->getPrimaryKey(), $this->id));
	}

	public function loadFromDb() {
		if($this->loaded) return;
		if(!$this->id) return false;
		$dbfields = self::$db->getRow(self::$db->prepare("SELECT * FROM @VAR WHERE @VAR=@VAL LIMIT 1", $this->getTable(), $this->getPrimaryKey(), $this->id));
		$this->dbfields = $dbfields && is_array($dbfields) ? $dbfields : array();
		$primary = $this->getPrimaryKey();
		if($this->dbfields && isset($this->dbfields[$primary])) {
			$this->id = $this->dbfields[$primary];
		}
		$this->loaded = true;
		$this->changed = false;
		$this->changed_fields = array();
		return $this;
	}

	public function saveToDb($new_if_no_id=true, $force_new=false) {
		if($this->isReadOnly()) return;
		if(!$this->changed || !$this->dbfields) return false;
		if(!$this->id && !$new_if_no_id) return;
		$generated_id = false;
		$update = false;
		if($this->id) {
			$update = true;
		}else{
			$exists = true;
			$tries = 0;
			while($exists) {
				$tries++;
				if($tries>255) {
					return Error::fatal('ID generation failed: Infinite loop.');
				}
				if($generated_id = $this->generateId()) {
					self::$db->lockTable($this->getTable());
					$exists = self::$db->single(self::$db->prepare("SELECT @VAR FROM @VAR WHERE @VAR=@VAL LIMIT 1", $this->getPrimaryKey(), $this->getTable(), $this->getPrimaryKey(), $generated_id));
				}else{
					$exists = false;
				}
			}
			$this->dbfields[$this->getPrimaryKey()] = $generated_id;
			$this->id = $generated_id;
		}
		if($force_new) $update = false;
		if($update) {
			$query = "UPDATE `".self::$db->escape($this->getTable())."` SET ";
		}else{
			$query = "INSERT INTO `".self::$db->escape($this->getTable())."` SET ";
		}
		$set = array();
		foreach($this->dbfields as $name=>$value) {
			$set[] = "`".self::$db->escape($name)."`=".($value===null ? 'NULL' : "'".self::$db->escape($value)."'");
		}
		$query.= implode(', ',$set);
		if($update) {
			$query.= self::$db->prepare(" WHERE @VAR=@VAL LIMIT 1", $this->getPrimaryKey(), $this->id);
		}
		$this->changed = false;
		$this->changed_fields = array();
		if($generated_id===null) {
			$id = self::$db->getId($query);
			if(!$id) return false;
			$this->dbfields[$this->getPrimaryKey()] = $id;
			$this->id = $id;
			return (bool) $id;
		}else{
			$result = self::$db->query($query);
			self::$db->unlockTables();
			if(!$result) return false;
			return $result;
		}
	}

	public function saveAsNew() {
		return $this->saveToDb(true, true);
	}

	public function save($new=false) {
		return $new ? $this->saveAsNew() : $this->saveToDb();
	}

	public function getField($name, $try_array=true) {
		$this->loadFromDb();
		if(!isset($this->dbfields[$name])) return null;
		if($try_array) {
			switch(substr($this->dbfields[$name], 0, 1)) {
				case '':
					return $this->dbfields[$name];
					break;
				case '{':
				case '[':
					$json = @json_decode($this->dbfields[$name], true);
					if($json!==null) return $json;
					break;
			}
		}
		return $this->dbfields[$name];
	}

	public function getFields() {
		$this->loadFromDb();
		return $this->dbfields;
	}

	public function getFieldHtml($name, $empty_placeholder=null) {
		$this->loadFromDb();
		$value = $this->getField($name, false);
		return $value ? htmlspecialchars($value) : ($empty_placeholder===null ? Error::emptyField() : $empty_placeholder);
	}

	public function setField($name, $value) {
		if($this->isReadOnly()) {
			Error::warning('Trying to set field of read-only DB record.');
			return;
		}
		if(is_object($value) && $value instanceof AbstractFormField) $value = $value->getUserValue();
		if(is_array($value)) $value = json_encode($value);
		if(isset($this->dbfields[$name]) && $this->dbfields[$name]===$value) return $this;
		$this->dbfields[$name] = $value;
		if($name===$this->getPrimaryKey()) $this->setId($value);
		$this->changed = true;
		$this->changed_fields[] = $name;
		return $this;
	}

	public function importForm(Form $form, $whitelist=array(), $blacklist=array()) {
		if($this->isReadOnly()) {
			Error::warning('Trying to import form to read-only DB record.');
			return;
		}
		$fields = $form->getFields();
		if(!$fields) return $this;
		foreach($fields as $field) {
			$name = $field->getName();
			if($blacklist && in_array($name, $blacklist)) continue;
			if($whitelist && !in_array($name, $whitelist)) continue;
			$this->setField($name, $field->getUserValue());
		}
		return $this;
	}

	public function importDbFields($dbfields) {
		if($this->isReadOnly()) {
			Error::warning('Trying to import DB fields into read-only DB record.');
			return;
		}
		$this->id = $dbfields[$this->getPrimaryKey()];
		$this->dbfields = $dbfields;
		$this->changed = false;
		$this->changed_fields = array();
		$this->loaded = true;
	}

	public function getChangedFields() {
		return $this->changed_fields;
	}

	public function getDuplicateError() {
		$error = self::$db->getLastError();
		if(!$error) return false;
		if(preg_match('/^Duplicate entry \'[^\']+\' for key \'([^\']+)\'/', $error, $matches)) {
			return $matches[1];
		}
		Error::fatal($error);
		return null;
	}

	public function exists() {
		if(!$this->loaded) $this->loadFromDb();
		return (bool) $this->dbfields;
	}

}
