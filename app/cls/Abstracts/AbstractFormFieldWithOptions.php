<?php

/**
 * Form Field with options.
 * 
 * All extending classes **must** declare the following `protected` method:
 * <code>
 * protected function init() {
 * 	$this->setType('select');
 * 	$this->setCssClass('dropdown');
 * }
 * </code>
 */
abstract class AbstractFormFieldWithOptions extends AbstractFormField {

	protected $blank_option = false;
	protected $options = array();

	/**
	 * Sets the available options.
	 * 
	 * @access public
	 * @param array $options
	 * @return self
	 */
	public function setOptions($options) {
		$this->options = $options;
		return $this;
	}

	/**
	 * Specifies if a blank option should be added at the beginning of the selection list.
	 * 
	 * @access public
	 * @param bool $blank (default: true)
	 * @return self
	 */
	public function setBlankOption($blank=true) {
		$this->blank_option = (bool) $blank;
		return $this;
	}

	/**
	 * Adds an option to the list of selectable values.
	 * 
	 * @access public
	 * @param string $option_value
	 * @param string $option_label (optional) If set this text will be presented to the user instead of the `$option_value` (default: null)
	 * @return self
	 */
	public function addOption($option_value, $option_label=null) {
		if($option_label===null) $option_label = $option_value;
		$this->options[$option_value] = $option_label;
		return $this;
	}

	/** @internal */
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
