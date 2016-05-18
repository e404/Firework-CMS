<?php

/**
 * Form Field Text field with currency input.
 */
class CurrencyField extends TextField {

	protected function init() {
		$this->setType('number');
		$this->setCssClass('text');
	}

	public function getHtml() {
		$html = parent::getHtml();
		$html = str_replace('<input ', '<input step="0.01" min="0" ', $html);
		return $html;
	}

}
