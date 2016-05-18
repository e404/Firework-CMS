<?php

/**
 * `FormField` Submit button.
 */
class SubmitButton extends Button {

	protected function init() {
		parent::init();
		$this->setType('submit');
	}

}
