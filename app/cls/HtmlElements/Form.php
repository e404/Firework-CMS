<?php

class Form extends AbstractHtmlElement {

	protected static $count = 0;

	protected $fields = array();
	protected $method = 'post';
	protected $sent = false;
	protected $attr = array();

	public function __construct($method=null) {
		if($method!==null) $this->setMethod($method);
	}

	public function wasSent($sent=null) {
		return $this->sent;
	}

	public function setMethod($method) {
		$this->method = strtolower($method);
	}

	public function getMethod() {
		return $this->method;
	}

	public function setAttr($attr, $value) {
		$this->attr[$attr] = $value;
		return $this;
	}

	public function addField(AbstractFormField $field) {
		$this->fields[$field->getName()] = $field;
		$field->setParentForm($this);
		if($field->getUserValue()!==null) $this->sent = true;
		return $this;
	}

	public function changeFieldName($oldName, $newName) {
		$this->fields[$newName] = $this->fields[$oldName];
		unset($this->fields[$oldName]);
	}

	public function getField($name, $no_error=false) {
		if(!isset($this->fields[$name])) {
			if(!$no_error) Error::warning('Field not found: '.$name);
			return null;
		}
		return $this->fields[$name];
	}

	public function getFields() {
		return $this->fields;
	}

	public function getHtml() {
		return '<!-- Form -->';
	}

	public function beginForm($auto_error_handling=false) {
		self::$count++;
		header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
		header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache');
		$html = '';
		$link = App::getLink();
		if($auto_error_handling) {
			$anchor = 'form'.self::$count;
			$link.= '#'.$anchor;
			$html.= '<div id="'.$anchor.'" class="form-anchor"></div>';
		}
		$html.= '<form method="'.($this->method==='post' ? 'post" enctype="multipart/form-data' : $this->method).'" action="'.$link.'"';
		foreach($this->attr as $attr=>$value) {
			$html.= ' '.$attr.'="'.htmlspecialchars($value).'"';
		}
		$html.= ">\n";

		if($auto_error_handling) {
			
		}

		echo $html;
	}

	public function endForm() {
		echo "</form>\n";
	}

	public function getErrors() {
		if(!$this->wasSent()) return array();
		$errors = array();
		foreach($this->fields as $field) {
			if($error_msg = $field->hasError()) {
				$errors[$field->getName()] = $error_msg===true ? '' : $error_msg;
			}
		}
		return $errors;
	}

	public function chainDbRecord(AbstractDbRecord $record, $skip=array()) {
		$return = array(
			'changed' => array(),
			'error' => array()
		);
		if($skip && !is_array($skip)) $skip = array($skip);
		foreach($record->getFields() as $key=>$value) {
			if(in_array($key, $skip)) continue;
			$field = $this->getField($key, true);
			if(!$field) continue;
			$field->injectUserValue($value);
			if($errormsg = $field->hasError()) {
				$return['error'][$key] = $error_msg===true ? '' : $error_msg;
				continue;
			}
			$record->setField($key, $field->getUserValue());
		}
		$return['changed'] = $record->getChangedFields();
		$record->saveToDb();
		return $return;
	}

}
