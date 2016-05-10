<?php

abstract class AbstractFormFieldWithOptions extends AbstractFormField {

	protected $blank_option = false;
	protected $options = array();

	public function setOptions($options) {
		$this->options = $options;
		return $this;
	}

	public function setBlankOption($blank=true) {
		$this->blank_option = !!$blank;
		return $this;
	}

	public function addOption($option_value, $option_label=null) {
		if($option_label===null) $option_label = $option_value;
		$this->options[$option_value] = $option_label;
		return $this;
	}

	public function hasError() {
		if(parent::hasError()) return true;
		if($value = $this->getUserValue()) {
			if(!$this->options) {
				Error::warning('No options set.');
				return true;
			}
			if(!isset($this->options[$value])) return true;
		}
		return false;
	}

}
