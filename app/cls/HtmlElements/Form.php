<?php

/**
 * HTML `Form`.
 */
class Form extends AbstractHtmlElement {

	protected static $count = 0;

	protected $formcode = '';
	protected $fields = array();
	protected $method = 'post';
	protected $sent = null;
	protected $attr = array();
	protected $id = '';
	protected $submit_handler = null;

	/** @internal */
	public function __construct($method=null) {
		$bt = debug_backtrace()[0];
		$this->formcode = 'form_'.md5($bt['file'].':'.$bt['line'].':'.filemtime($bt['file']));
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
	 * @access public
	 * @return bool `true` if the form was sent, `false` otherwise
	 */
	public function wasSent() {
		if($this->sent!==null) return $this->sent;
		switch($this->method) {
			case 'post':
				$this->sent = isset($_POST[$this->formcode]);
				break;
			case 'get':
				$this->sent = isset($_GET[$this->formcode]);
				break;
		}
		return $this->sent;
	}

	/**
	 * Sets the request mode.
	 * 
	 * @access public
	 * @param string $method `'get'` or `'post'`
	 * @return void
	 */
	public function setMethod($method) {
		$this->method = strtolower($method);
	}

	/**
	 * Returns the request method.
	 * 
	 * @access public
	 * @return string `'get'` or `'post'`
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
	 * Defines a function that handles errors after Form submit.
	 * The handler itself should return `true` on success and `false` on errors.
	 * 
	 * @access public
	 * @param callable $fn
	 * @return Form
	 */
	public function setSubmitHandler(callable $fn) {
		$this->submit_handler = $fn;
		return $this;
	}

	/**
	 * Handles the Form submit event.
	 * If not defined via `Form::setSubmitHandler`, a default handler is applied.
	 * 
	 * @access public
	 * @return bool Should return `true` on success, `false` on errors.
	 * @see self::setSubmitHandler()
	 */
	public function handleSubmit() {
		if(!$this->submit_handler) Error::fatal('No submit handler found.');
		return call_user_func($this->submit_handler, $this);
	}

	/**
	 * Writes the opening `<form>` tag to the output and sets some non-caching headers.
	 * 
	 * ***TODO:*** Implement `$auto_error_handling`.
	 *
	 * @access public
	 * @param bool $use_anchor_link (default: false)
	 * @return void
	 */
	public function beginForm($use_anchor_link=false) {
		self::$count++;
		header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
		header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
		$html = '';
		$link = App::getLink();
		if($use_anchor_link) {
			$anchor = 'form'.self::$count;
			$link.= '#'.$anchor;
			$html.= '<div id="'.$anchor.'" class="form-anchor"></div>';
		}
		$html.= '<form'.($this->id ? ' id="'.$this->id.'"' : '').' method="'.($this->method==='post' ? 'post" enctype="multipart/form-data' : $this->method).'" action="'.$link.'"';
		foreach($this->attr as $attr=>$value) {
			$html.= ' '.$attr.'="'.htmlspecialchars($value).'"';
		}
		$html.= '><input type="hidden" name="'.$this->formcode.'" value="1">'."\n";
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
			if($error_msg = $field->hasError()) {
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
