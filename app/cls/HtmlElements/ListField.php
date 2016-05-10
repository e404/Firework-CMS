<?php

class ListField extends DropdownField {

	protected $size = 5;

	protected function init() {
		$this->setType('select');
		$this->setCssClass('list');
	}

	public function setSize($size) {
		$this->size = $size>=1 ? (int) $size : 1;
	}

	public function getHtml() {
		$html = $this->getGenericHtml();
		$html = str_replace('<select name=','<select size="'.$this->size.'" name=',$html);
		return $html;
	}

}
