<?php

/**
 * Form Field Text field.
 */
class TextField extends AbstractFormField {

	protected $multiline = false;
	protected $jsautocorrect = null;
	protected $placeholder = null;

	protected function init() {
		$this->setType('text');
		$this->setCssClass('text');
	}

	public function setMultiline($multiline=true) {
		$this->multiline = (bool) $multiline;
		return $this;
	}

	public function setJsAutocorrect($jsfn) {
		$this->jsautocorrect = trim(preg_replace('/\s+/',' ',$jsfn));
		return $this;
	}

	public function setPlaceholder($placeholder_text) {
		$this->placeholder = $placeholder_text;
		return $this;
	}

	public function getHtml() {
		$userValue = $this->getAutofillValue();
		$html = '<label><span>'.$this->getLabelHtml().' </span>';
		$onchange = '';
		if($this->jsautocorrect) {
			$onchange = ' onchange="this.value=('.str_replace('"','&quot;',$this->jsautocorrect).')(this.value); return false;"';
		}
		if($this->multiline) {
			$html.= '<textarea name="'.$this->name.'"'.($this->maxlength ? ' maxlength="'.$this->maxlength.'"' : '').($this->placeholder ? ' placeholder="'.htmlspecialchars($this->placeholder).'"' : '').$onchange.'>'.htmlspecialchars($userValue===null ? $this->value : $userValue).'</textarea>';
		}else{
			$html.= '<input type="'.$this->type.'" name="'.$this->name.'"'.($this->maxlength ? ' maxlength="'.$this->maxlength.'"' : '').($this->placeholder ? ' placeholder="'.htmlspecialchars($this->placeholder).'"' : '').' value="'.htmlspecialchars($userValue===null ? $this->value : $userValue).'"'.$onchange.'>';
		}
		$html.= '</label>';
		return parent::getHtml($html);
	}

}
