<?php

/**
 * Form Field Text field.
 */
class TextField extends AbstractFormField {

	protected $area = false;
	protected $jsautocorrect = null;

	protected function init() {
		$this->setType('text');
		$this->setCssClass('text');
	}

	public function setArea($area=true) {
		$this->area = !!$area;
		return $this;
	}

	public function setJsAutocorrect($jsfn) {
		$this->jsautocorrect = trim(preg_replace('/\s+/',' ',$jsfn));
		return $this;
	}

	public function getHtml() {
		$userValue = $this->getAutofillValue();
		$html = '<label><span>'.$this->getLabelHtml().' </span>';
		$onchange = '';
		if($this->jsautocorrect) {
			$onchange = ' onchange="this.value=('.str_replace('"','&quot;',$this->jsautocorrect).')(this.value); return false;"';
		}
		if($this->area) {
			$html.= '<textarea name="'.$this->name.'"'.$onchange.'>'.htmlspecialchars($userValue===null ? $this->value : $userValue).'</textarea>';
		}else{
			$html.= '<input type="'.$this->type.'" name="'.$this->name.'" value="'.htmlspecialchars($userValue===null ? $this->value : $userValue).'"'.$onchange.'>';
		}
		$html.= '</label>';
		return parent::getHtml($html);
	}

}
