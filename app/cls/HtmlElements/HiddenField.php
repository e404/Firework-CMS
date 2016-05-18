<?php

/**
 * `FormField` Hidden field.
 */
class HiddenField extends AbstractFormField {

	protected function init() {
		$this->setType('hidden');
	}

	public function getHtml() {
		$userValue = $this->getAutofillValue();
		return '<input type="'.$this->type.'" name="'.$this->name.'" value="'.htmlspecialchars($userValue===null ? $this->value : $userValue).'">';
	}

}
