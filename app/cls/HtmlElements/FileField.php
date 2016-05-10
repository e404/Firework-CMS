<?php

class FileField extends AbstractFormField {

	public function getUserValue() {
		return null;
	}

	protected function init() {
		$this->setType('file');
		$this->setCssClass('file');
	}

	public function getFile() {
		if(!isset($_FILES[$this->name]) || !isset($_FILES[$this->name]['tmp_name'])) return null;
		return $_FILES[$this->name]['tmp_name'];
	}

	public function removeFile() {
		$file = $this->getFile();
		if(!$file) return;
		return @unlink($file);
	}

	public function hasError() {
		return $this->required && !$this->getFile();
	}

}
