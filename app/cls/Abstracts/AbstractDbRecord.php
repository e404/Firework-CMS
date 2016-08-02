<?php

/**
 * Database Record.
 *
 * Extending classes must implement all abstract methods from `AbstractDbEntity`.<br>
 *
 * Additionally, the following `protected` method **should** be defined:
 * <code>
 * protected function generateId() {
 * 	return Random::generateString(6);
 * 	// You don't need to check for duplicate keys, this is done automatically.
 * 	// If null is returned, the database should handle the primary key assignment.
 * }
 * </code>
 *
 * @see AbstractDbEntity
 */
abstract class AbstractDbRecord extends AbstractDbEntity {

	protected $id = null;
	protected $dbfields = array();
	protected $changed_fields = array();

	/**
	 * Constructs the record.
	 * 
	 * @access public
	 * @param mixed $id (optional) If the `$id` is set, the record content is loaded (default: null)
	 * @return void
	 */
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

	/**
	 * Sets the ID of the record.
	 *
	 * If the record with the specified `$id` already exists, `loadFromDb()` has to be called manually afterwards.
	 * 
	 * @access public
	 * @param string $id
	 * @return void
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Returns the ID of the record.
	 * 
	 * @access public
	 * @return string Returns the ID or `null` if no ID is present
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Removes the record in the database.
	 * 
	 * @access public
	 * @return bool `true` on success, `false` on error
	 */
	public function delete() {
		if(!$this->id) return false;
		return (bool) self::$db->query(self::$db->prepare("DELETE FROM @VAR WHERE @VAR=@VAL LIMIT 1", $this->getTable(), $this->getPrimaryKey(), $this->id));
	}

	/**
	 * Clones a database record <strong>without saving it</strong>.
	 *
	 * The ID is removed after cloning. When saving, a new ID will be generated.
	 *
	 * @access public
	 * @return AbstractDbRecord
	 * @see self::save()
	 */
	public function duplicate() {
		if(!$this->getId() || !$this->exists()) {
			Error::warning('Tried to duplicate non-existing DB record.');
			return null;
		}
		$new_record = clone $this;
		$new_record->setId(null);
		$new_record->setField($this->getPrimaryKey(), null);
		return $new_record;
	}

	/**
	 * Loads the record's datbase content.
	 * 
	 * @access public
	 * @return bool Returns `true` on success, `false` if no ID has been set
	 */
	public function loadFromDb() {
		if($this->loaded) return true;
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
		return true;
	}

	/**
	 * Stores the record in the database.
	 * 
	 * @access public
	 * @param bool $new_if_no_id (optional) If `true`, all objects with no ID assigned will create a new database entity (default: true)
	 * @param bool $force_new (optional) If `true`, the current record will be saved as new record (default: false)
	 * @return bool `true` on success, `false` on error
	 */
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

	/**
	 * Shortcut for `saveToDb()`.
	 * 
	 * @access public
	 * @param bool $new (optional) If `true`, a new record will be saved (default: false)
	 * @see self::saveToDb()
	 */
	public function save($new=false) {
		return $new ? $this->saveToDb(true, true) : $this->saveToDb();
	}

	/**
	 * Returns a field of the database record.
	 * 
	 * @access public
	 * @param string $name The field's name
	 * @param bool $try_array (optional) If set to `true`, the method tries to detect and return an array as value (default: true)
	 * @return mixed
	 */
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

	public function getBool($fieldname) {
		return !!$this->getField($fieldname, false);
	}

	public function getInt($fieldname) {
		return (int) $this->getField($fieldname, false);
	}

	public function getFloat($fieldname) {
		return (float) $this->getField($fieldname, false);
	}

	public function getArray($fieldname) {
		$val = $this->getField($fieldname, true);
		return is_array($val) ? $val : array();
	}

	/**
	 * Returns all database fields (columns).
	 * 
	 * @access public
	 * @return array
	 */
	public function getFields() {
		$this->loadFromDb();
		return $this->dbfields;
	}

	/**
	 * Returns an HTML escaped field value.
	 * 
	 * @access public
	 * @param string $name The field's name
	 * @param string $empty_placeholder (optional) If set, this value is returned if the field is empty; otherwise the value of `Error::emptyField()` is returned (default: null)
	 * @return string
	 * @see Error::setEmptyFieldPlaceholder()
	 */
	public function getFieldHtml($name, $empty_placeholder=null) {
		$this->loadFromDb();
		$value = $this->getField($name, false);
		return $value ? htmlspecialchars($value) : ($empty_placeholder===null ? Error::emptyField() : $empty_placeholder);
	}

	/**
	 * Sets a field value.
	 * 
	 * @access public
	 * @param string $name The field's name
	 * @param mixed $value The field's value (`string` or `array`)
	 * @return self
	 */
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

	/**
	 * Imports values from a `Form`.
	 * 
	 * @access public
	 * @param Form $form
	 * @param array $whitelist (optional) If set, only field names within this array are imported (default: array())
	 * @param array $blacklist (optional) If set, field names within this array are not imported (default: array())
	 * @return self
	 */
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

	/**
	 * Batch sets field values.
	 * 
	 * @access public
	 * @param array $dbfields An array containing field names as keys and their corresponding values
	 * @return void
	 */
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

	/**
	 * Returns an `array` of fields that have changed since the record has been loaded or saved.
	 * 
	 * @access public
	 * @return array
	 */
	public function getChangedFields() {
		return $this->changed_fields;
	}

	/**
	 * Returns the field name which raised a `Duplicate entry` SQL error.
	 * 
	 * @access public
	 * @return string Returns the field name which raised the error, `false` if there is no error and throws a fatal error if other errors occured
	 */
	public function getDuplicateError() {
		$error = self::$db->getLastError();
		if(!$error) return false;
		if(preg_match('/^Duplicate entry \'[^\']+\' for key \'([^\']+)\'/', $error, $matches)) {
			return $matches[1];
		}
		Error::fatal($error);
		return null;
	}

	/**
	 * Checks if the record with the previously set ID exists in the database.
	 * 
	 * @access public
	 * @return bool `true` if it exists, `false` otherwise
	 */
	public function exists() {
		if(!$this->loaded) $this->loadFromDb();
		return (bool) $this->dbfields;
	}

}
