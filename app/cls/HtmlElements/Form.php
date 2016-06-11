<?php

/**
 * HTML `Form`.
 */
class Form extends AbstractHtmlElement {

	protected static $count = 0;

	protected $fields = array();
	protected $method = 'post';
	protected $sent = false;
	protected $attr = array();
	protected $id = '';

	/** @internal */
	public function __construct($method=null) {
		if($method!==null) $this->setMethod($method);
	}

	/**
	 * Sets the `<form>` id.
	 * 
	 * @access public
	 * @param string $id
	 * @return void
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Checks if the form has been sent.
	 *
	 * ***TODO:*** Add a more sophisticated check with a hidden field and the presence of it after sending.
	 * 
	 * @access public
	 * @return bool `true` if the form was sent, `false` otherwise
	 */
	public function wasSent() {
		return $this->sent;
	}

	/**
	 * Sets the request mode.
	 * 
	 * @access public
	 * @param string $method `'GET'` or `'POST'`
	 * @return void
	 */
	public function setMethod($method) {
		$this->method = strtolower($method);
	}

	/**
	 * Returns the request method.
	 * 
	 * @access public
	 * @return string
	 * @see self::setMethod()
	 */
	public function getMethod() {
		return $this->method;
	}

	/**
	 * Adds an attribute to the `<form>` element.
	 * 
	 * @access public
	 * @param string $attr The attribute's name
	 * @param string $value The attribute's value
	 * @return self
	 */
	public function setAttr($attr, $value) {
		$this->attr[$attr] = $value;
		return $this;
	}

	/**
	 * Adds a form field.
	 * 
	 * @access public
	 * @param AbstractFormField $field
	 * @return self
	 */
	public function addField(AbstractFormField $field) {
		$this->fields[$field->getName()] = $field;
		$field->setParentForm($this);
		if($field->getUserValue()!==null) $this->sent = true;
		return $this;
	}

	/**
	 * Changes the name of a form field previously defined.
	 * 
	 * @access public
	 * @param string $oldName
	 * @param string $newName
	 * @return void
	 * @see self::addField()
	 */
	public function changeFieldName($oldName, $newName) {
		$this->fields[$newName] = $this->fields[$oldName];
		unset($this->fields[$oldName]);
	}

	/**
	 * Returns a form field previously added.
	 * 
	 * @access public
	 * @param string $name
	 * @param bool $no_error (optional) If `true`, no error is triggered if the field could not be found (default: false)
	 * @return AbstractFormField
	 */
	public function getField($name, $no_error=false) {
		if(!isset($this->fields[$name])) {
			if(!$no_error) Error::warning('Field not found: '.$name);
			return null;
		}
		return $this->fields[$name];
	}

	/**
	 * Returns an `array` of all form fields.
	 * 
	 * @access public
	 * @return array
	 */
	public function getFields() {
		return $this->fields;
	}

	/** @internal */
	public function getHtml() {
		return '<!-- Form -->';
	}

	/**
	 * Writes the opening `<form>` tag to the output and sets some non-caching headers.
	 * 
	 * ***TODO:*** Implement `$auto_error_handling`.
	 *
	 * @access public
	 * @param bool $auto_error_handling (default: false)
	 * @return void
	 */
	public function beginForm($auto_error_handling=false) {
		self::$count++;
		header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
		header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
		$html = '';
		$link = App::getLink();
		if($auto_error_handling) {
			$anchor = 'form'.self::$count;
			$link.= '#'.$anchor;
			$html.= '<div id="'.$anchor.'" class="form-anchor"></div>';
		}
		$html.= '<form'.($this->id ? ' id="'.$this->id.'"' : '').' method="'.($this->method==='post' ? 'post" enctype="multipart/form-data' : $this->method).'" action="'.$link.'"';
		foreach($this->attr as $attr=>$value) {
			$html.= ' '.$attr.'="'.htmlspecialchars($value).'"';
		}
		$html.= ">\n";

		if($auto_error_handling) {
			// TODO
		}

		echo $html;
	}

	/**
	 * Writes the closing `</form>` tag.
	 * 
	 * @access public
	 * @return void
	 */
	public function endForm() {
		echo "</form>\n";
	}

	/**
	 * Returns all form field errors.
	 * 
	 * @access public
	 * @return array
	 */
	public function getErrors() {
		if(!$this->wasSent()) return array();
		$errors = array();
		foreach($this->fields as $field) {
			if($error_msg = $field->hasError()) {
				$errors[$field->getName()] = $error_msg===true ? '' : $error_msg;
			}
		}
		return $errors;
	}

	/**
	 * Attaches a database record to the form.
	 *
	 * If sending the form, the record will automatically be updated.
	 * 
	 * @access public
	 * @param AbstractDbRecord $record
	 * @param array $skip (optional) An array of form fields which must be ignored (default: array())
	 * @return array Returns all fields that have changed
	 */
	public function chainDbRecord(AbstractDbRecord $record, $skip=array()) {
		$return = array(
			'changed' => array(),
			'error' => array()
		);
		if($skip && !is_array($skip)) $skip = array($skip);
		foreach($record->getFields() as $key=>$value) {
			if(in_array($key, $skip)) continue;
			$field = $this->getField($key, true);
			if(!$field) continue;
			$field->injectUserValue($value);
			if($errormsg = $field->hasError()) {
				$return['error'][$key] = $error_msg===true ? '' : $error_msg;
				continue;
			}
			$record->setField($key, $field->getUserValue());
		}
		$return['changed'] = $record->getChangedFields();
		$record->saveToDb();
		return $return;
	}

}
