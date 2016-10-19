<?php

/**
 * Form Field Text field with email input.
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
		if($this->required && !$value) return true;
		return !EMail::validateAddress($value, !$this->ignoremx);
	}

}
