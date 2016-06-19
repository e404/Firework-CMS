<?php

/**
 * Form Field Password field.
 */
class PasswordField extends AbstractFormField {

	protected function init() {
		$this->setType('password');
		$this->setCssClass('text');
	}

	public function getPassHash() {
		$value = parent::getUserValue();
		if(!$value) return null;
		if(!preg_match('/^[0-9a-f]{64}$/',$value)) {
			for($i=0; $i<1000; $i++) {
				$value = hash('sha256',$value);
			}
		}
		return $value;
	}

}
