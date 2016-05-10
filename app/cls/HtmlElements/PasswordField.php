<?php

class PasswordField extends AbstractFormField {

	protected function isPassHash($str) {
		return !!preg_match('/^[0-9a-f]{64}$/',$str);
	}

	protected function makePassHash($str) {
		for($i=0; $i<1000; $i++) {
			$str = hash('sha256',$str);
		}
		return $str;
	}

	public function getPassHash() {
		$value = parent::getUserValue();
		if(!$value) return null;
		if($this->isPassHash($value)) return $value;
		return $this->makePassHash($value);
	}

	protected function init() {
		$this->setType('password');
		$this->setCssClass('text');
	}

	protected function getAutofillValue() {
		return $this->autofill ? $this->getPassHash() : null;
	}

	public function getHtml() {
		$hash = $this->getAutofillValue();
		return parent::getHtml('<label><span>'.$this->getLabelHtml().' </span><input type="'.$this->type.'" name="'.$this->name.'" value="'.htmlspecialchars($hash).'"></label>');
	}

}
