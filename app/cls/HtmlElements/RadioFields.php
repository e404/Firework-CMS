<?php

/**
 * Form Field Radio fields.
 *
 * This is a collection of radio input fields.
 */
class RadioFields extends AbstractFormFieldWithOptions {

	protected function init() {
		$this->setType('radio');
		$this->setCssClass('radio');
	}

	public function getHtml() {
		$html = '';
		foreach($this->options as $option_value=>$option_label) {
			$html.= $this->getChildHtml($option_value);
		}
		return parent::getHtml('<div class="radio-label"><span>'.$this->getLabelHtml().' </span></div>'.$html);
	}

	public function getChildHtml($option_value) {
		if(!isset($this->options[$option_value])) {
			Error::fatal('Option not found: '.$option_value);
		}
		$userValue = $this->getAutofillValue();
		return parent::getHtml('<label><input type="radio" name="'.$this->name.'" value="'.htmlspecialchars($option_value).'"'.($option_value===$userValue ? ' checked' : '').'><span> '.$this->options[$option_value].'</span></label>');
	}

}
