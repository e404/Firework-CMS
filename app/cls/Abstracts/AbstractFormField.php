<?php

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

	public function __construct() {
		$this->init();
	}

	public function setParentForm(Form $form) {
		$this->parentForm = $form;
	}

	public function getParentForm() {
		return $this->parentForm;
	}

	public function required($required=null) {
		if($required===null) return $this->required;
		$this->required = $required ? true : false;
		return $this;
	}

	public function hasError() {
		$value = $this->getUserValue();
		if(!$value) return $this->required;
		if(!$this->validation) return false;
		foreach($this->validation as $regex=>$error_msg) {
			if(substr($regex,0,1)==='!') {
				if(preg_match(substr($regex,1), $value)) return $error_msg;
			}else{
				if(!preg_match($regex, $value)) return $error_msg;
			}
		}
		return false;
	}

	public function setAutocorrect($fn) {
		if(!is_callable($fn)) Error::fatal('Callable function for applying autocorrect expected.');
		$this->autocorrect = $fn;
		return $this;
	}

	protected function applyAutocorrect($value) {
		if(!$this->autocorrect) return $value;
		$fn = $this->autocorrect;
		return $fn($value, $this->parentForm);
	}

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

	public function injectUserValue($value) {
		$this->injectedUserValue = $value;
		return $this;
	}

	public function getInjectedUserValue() {
		return $this->injectedUserValue;
	}

	public function getGenericHtml() {
		$userValue = $this->getAutofillValue();
		return '<label><span>'.$this->getLabelHtml().
			' </span><input type="'.$this->type.'"'.($this->name ? ' name="'.$this->name.'"' : '').
			' value="'.htmlspecialchars($userValue===null ? $this->value : $userValue).'"></label>';
	}

	public function getHtml($innerHtml=null) {
		$class = get_class($this);
		$reflector = new ReflectionMethod($class, 'getHtml');
		if($reflector->getDeclaringClass()->getName()!==$class) $innerHtml = $this->getGenericHtml();
		return '<div class="field '.$this->cssclass.'">'.$innerHtml.$this->getHintHtml().'</div>';
	}

	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	public function setName($name) {
		if($this->name && $this->parentForm instanceof Form) {
			$this->parentForm->changeFieldName($this->name, $name);
		}
		$this->name = $name;
		return $this;
	}

	public function getName() {
		return $this->name;
	}

	public function setLabel($label) {
		$this->label = $label;
		return $this;
	}

	public function getLabel() {
		return $this->label;
	}

	public function setText($text) {
		$this->text = $text;
		return $this;
	}

	public function getLabelHtml() {
		return $this->text ? $this->text : $this->label;
	}

	public function setValue($value) {
		$this->value = $value;
		return $this;
	}

	public function setCssClass($cssclass) {
		$this->cssclass = $cssclass;
		return $this;
	}

	public function addCssClass($cssclass) {
		$this->cssclass.= ' '.$cssclass;
		return $this;
	}

	public function setHint($hint) {
		$this->hint = $hint;
		return $this;
	}

	public function getHintHtml() {
		return $this->hint ? '<div class="hint">'.$this->hint.'</div>' : '';
	}

	public function autofill($autofill) {
		$this->autofill = (bool) $autofill;
		return $this;
	}

	protected function getAutofillValue() {
		return $this->autofill ? $this->getUserValue() : null;
	}

	public function addValidation($regex, $error_msg='') {
		$this->validation[$regex] = $error_msg;
		return $this;
	}

}
