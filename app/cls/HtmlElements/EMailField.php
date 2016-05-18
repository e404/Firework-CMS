<?php

/**
 * `FormField` Text field with email input.
 */
class EMailField extends AbstractFormField {

	protected $minlength = 0;
	protected $ignoremx = false;

	protected function init() {
		$this->setType('email');
		$this->setCssClass('text');
		$this->addValidation('/^[^@]+@.+$/');
	}

	public function ignoreMx($ignore=true) {
		$this->ignoremx = $ignore;
		return $this;
	}

	public function hasError() {
		$value = $this->getUserValue();
		if(($this->required && !$value) || !preg_match('/^[^@]+@[^@\.]+\.[^@]+$/',$value)) return true;
		if($this->ignoremx) return false;
		if(!getmxrr(substr($value,strpos($value,'@')+1),$mxhosts) || $mxhosts===gethostbyname($mxhosts[0])) return true;
		return false;
	}

}
