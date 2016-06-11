<?php

/**
 * Form Field Button.
 */
class Button extends AbstractFormField {

	protected $action = '';
	protected $disabled = false;

	protected function init() {
		$this->setType('button');
		$this->setCssClass('button');
	}

	public function disabled($disabled=null) {
		if($disabled===null) return $this->disabled;
		$this->disabled = (bool) $disabled;
		return $this;
	}

	public function setAction($action) {
		$this->action = $action;
	}

	public function getHtml() {
		$html = $this->getGenericHtml();
		if($this->action) {
			$html = str_replace('<input ','<input onclick="'.$action.'" ',$html);
		}
		if($this->disabled) {
			$html = str_replace('<input ','<input disabled="disabled" ',$html);
		}
		return parent::getHtml($html);
	}

}
