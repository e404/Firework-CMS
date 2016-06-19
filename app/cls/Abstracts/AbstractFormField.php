<?php

/**
 * Form Field.
 *
 * All extending classes **must** declare the following `protected` method:
 * <code>
 * protected function init() {
 * 	$this->setType('select');
 * 	$this->setCssClass('dropdown');
 * }
 * </code>
 */
abstract class AbstractFormField extends AbstractHtmlElement {

	protected $parentForm = null;
	protected $required = false;
	protected $type;
	protected $name = '';
	protected $label = '';
	protected $text = '';
	protected $value = '';
	protected $injectedUserValue = null;
	protected $cssclass = '';
	protected $hint = '';
	protected $autofill = true;
	protected $autocorrect = null;
	protected $validation = array();

	abstract protected function init();

	/** @internal */
	public function __construct() {
		$this->init();
	}

	/**
	 * Specifies the parent `Form`.
	 * 
	 * @access public
	 * @param Form $form
	 * @return void
	 * @see Form
	 */
	public function setParentForm(Form $form) {
		$this->parentForm = $form;
	}

	/**
	 * Returns the parent `Form`.
	 * 
	 * @access public
	 * @return Form
	 * @see Form
	 */
	public function getParentForm() {
		return $this->parentForm;
	}

	/**
	 * Checks if the field is required or sets it required.
	 * 
	 * @access public
	 * @param bool $required (optional) If omitted, the required status is returned (`bool`); if `true` or `false` the required status is set accordingly (default: null)
	 */
	public function required($required=null) {
		if($required===null) return $this->required;
		$this->required = $required ? true : false;
		return $this;
	}

	/**
	 * Checks if the form field has an error.
	 * 
	 * @access public
	 * @return bool `true` if the field is errorous, `false` otherwise
	 */
	public function hasError() {
		$value = $this->getUserValue();
		if(!$value) return $this->required;
		if(!$this->validation) return false;
		foreach($this->validation as $validation) {
			list($regex_or_callback, $error_msg) = $validation;
			if(!$error_msg) $error_msg = true;
			if(is_callable($regex_or_callback)) {
				return $regex_or_callback($value) ? false : $error_msg;
			}elseif(substr($regex_or_callback,0,1)==='!') {
				if(preg_match(substr($regex_or_callback,1), $value)) return $error_msg;
			}else{
				if(!preg_match($regex_or_callback, $value)) return $error_msg;
			}
		}
		return false;
	}

	/**
	 * Defines an autocorrect handler.
	 *
	 * The function `$fn` is applied to the field value
	 *
	 * @access public
	 * @param callable $fn
	 * @return self
	 */
	public function setAutocorrect(callable $fn) {
		$this->autocorrect = $fn;
		return $this;
	}

	protected function applyAutocorrect($value) {
		if(!$this->autocorrect) return $value;
		$fn = $this->autocorrect;
		return $fn($value, $this->parentForm);
	}

	/**
	 * Returns the value of the form field the user entered.
	 * 
	 * @access public
	 * @return string
	 */
	public function getUserValue() {
		if(!$this->name) return null;
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		if($this->parentForm && $this->parentForm->getMethod()!==$method) return $this->getInjectedUserValue();
		switch($method) {
			case 'get':
				return $this->applyAutocorrect(isset($_GET[$this->name]) ? $_GET[$this->name] : $this->getInjectedUserValue());
				break;
			case 'post':
				return $this->applyAutocorrect(isset($_POST[$this->name]) ? $_POST[$this->name] : $this->getInjectedUserValue());
				break;
		}
		return $this->applyAutocorrect($this->getInjectedUserValue());
	}

	/**
	 * Applies a value to the form field as if the user had been entering it.
	 * 
	 * @access public
	 * @param string $value
	 * @return self
	 */
	public function injectUserValue($value) {
		$this->injectedUserValue = $value;
		return $this;
	}

	/**
	 * Returns the injected value, if one is present.
	 * 
	 * @access public
	 * @return string
	 * @see self::injectUserValue()
	 */
	public function getInjectedUserValue() {
		return $this->injectedUserValue;
	}

	/**
	 * Returns a generic HTML representation of the form field.
	 * 
	 * @access public
	 * @return string
	 */
	public function getGenericHtml() {
		$userValue = $this->getAutofillValue();
		return '<label><span>'.$this->getLabelHtml().
			' </span><input type="'.$this->type.'"'.($this->name ? ' name="'.$this->name.'"' : '').
			' value="'.htmlspecialchars($userValue===null ? $this->value : $userValue).'"></label>';
	}

	/**
	 * Returns the field's HTML representation.
	 * 
	 * @access public
	 * @return string
	 */
	public function getHtml($innerHtml=null) {
		$class = get_class($this);
		$reflector = new ReflectionMethod($class, 'getHtml');
		if($reflector->getDeclaringClass()->getName()!==$class) $innerHtml = $this->getGenericHtml();
		return '<div class="field '.$this->cssclass.'">'.$innerHtml.$this->getHintHtml().'</div>';
	}

	/**
	 * Defines the type of the form field.
	 *
	 * Standard HTML form field types should apply.
	 * 
	 * @access public
	 * @param string $type
	 * @return self
	 */
	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	/**
	 * Sets the field's name.
	 * 
	 * @access public
	 * @param string $name
	 * @return self
	 */
	public function setName($name) {
		if($this->name && $this->parentForm instanceof Form) {
			$this->parentForm->changeFieldName($this->name, $name);
		}
		$this->name = $name;
		return $this;
	}

	/**
	 * Returns the field's name.
	 * 
	 * @access public
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Sets the field's label.
	 *
	 * The label is presented to the user and should give the label a public name.
	 * 
	 * @access public
	 * @param string $label
	 * @return self
	 */
	public function setLabel($label) {
		$this->label = $label;
		return $this;
	}

	/**
	 * Returns the field's label.
	 * 
	 * @access public
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * Sets the field's text.
	 *
	 * The text replaces the field's label, though the label is still used in error messages.
	 * The text can describe the label in a more detailed way.
	 * 
	 * @access public
	 * @param string $text
	 * @return self
	 * @see self::setLabel()
	 */
	public function setText($text) {
		$this->text = $text;
		return $this;
	}

	/**
	 * Returns the field's label.
	 * 
	 * @access public
	 * @return string
	 */
	public function getLabelHtml() {
		return $this->text ? $this->text : $this->label;
	}

	/**
	 * Sets the label's default value.
	 *
	 * **Warning:** The user input is set automatically and can be retrieved via `getUserValue`.
	 * 
	 * @access public
	 * @param string $value
	 * @return self
	 * @see self::getUserValue()
	 */
	public function setValue($value) {
		$this->value = $value;
		return $this;
	}

	/**
	 * Defines the CSS class of the field.
	 * 
	 * @access public
	 * @param string $cssclass
	 * @return self
	 */
	public function setCssClass($cssclass) {
		$this->cssclass = $cssclass;
		return $this;
	}

	/**
	 * Adds an additional CSS class to the field.
	 * 
	 * @access public
	 * @param mixed $cssclass
	 * @return self
	 */
	public function addCssClass($cssclass) {
		$this->cssclass.= ' '.$cssclass;
		return $this;
	}

	/**
	 * Adds a fillout hint to the field.
	 *
	 * The hint message can contain HTML content and should help the user to propperly fill out the form field.
	 * 
	 * @access public
	 * @param string $hint
	 * @return self
	 */
	public function setHint($hint) {
		$this->hint = $hint;
		return $this;
	}

	/**
	 * Returns the hint wrapped by a `<div>` with the CSS class `hint`.
	 * 
	 * @access public
	 * @return void
	 */
	public function getHintHtml() {
		return $this->hint ? '<div class="hint">'.$this->hint.'</div>' : '';
	}

	/**
	 * Enables or disables browser autofill.
	 * 
	 * @access public
	 * @param bool $autofill
	 * @return void
	 */
	public function autofill($autofill) {
		$this->autofill = (bool) $autofill;
		return $this;
	}

	protected function getAutofillValue() {
		return $this->autofill ? $this->getUserValue() : null;
	}

	/**
	 * Adds a regular expression or callback function that is executed on field validation.
	 * 
	 * @access public
	 * @param string $regex_or_callback The regular expression or callback function
	 * @param string $error_msg (optional) If set, this error message is presented to the user if the regular expression does not match the field's user input (default: '')
	 * @return void
	 */
	public function addValidation($regex_or_callback, $error_msg='') {
		$this->validation[] = array($regex_or_callback, $error_msg);
		return $this;
	}

}
