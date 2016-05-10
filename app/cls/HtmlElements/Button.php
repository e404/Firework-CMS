<?php

class Button extends AbstractFormField {

	protected $action = '';

	protected function init() {
		$this->setType('button');
		$this->setCssClass('button');
	}

	public function setAction($action) {
		$this->action = $action;
	}

	public function getHtml() {
		$html = $this->getGenericHtml();
		if($this->action) {
			$html = str_replace('<input ','<input onclick="'.$action.'" ',$html);
		}
		return parent::getHtml($html);
	}

}
