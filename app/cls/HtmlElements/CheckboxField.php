<?php

class CheckboxField extends AbstractFormField {

	protected $area = false;

	protected function init() {
		$this->setType('checkbox');
		$this->setCssClass('checkbox');
	}

	public function getHtml() {
		$userValue = $this->getAutofillValue();
		return parent::getHtml('<label><input type="'.$this->type.'" name="'.$this->name.'" value="1"'.($userValue ? ' checked' : '').'> <span>'.$this->getLabelHtml().'</span></label>');
	}

}
