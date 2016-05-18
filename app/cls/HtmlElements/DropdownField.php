<?php

/**
 * `FormField` Dropdown field.
 */
class DropdownField extends AbstractFormFieldWithOptions {

	protected function init() {
		$this->setType('select');
		$this->setCssClass('dropdown');
	}

	public function getHtml() {
		$userValue = $this->getAutofillValue();
		$html = '<label><span>'.$this->getLabelHtml().' </span>';
		$html.= '<select name="'.$this->name.'">';
		if($this->blank_option) {
			$html.= '<option value="">—</option>';
		}
		if($this->options && is_array($this->options)) {
			foreach($this->options as $option_value=>$option_label) {
				$html.= '<option value="'.htmlspecialchars($option_value).'"'.($option_value===$userValue ? ' selected' : '').'>'.htmlspecialchars($option_label).'</option>';
			}
		}
		$html.= '</select>';
		$html.= '</label>';
		return parent::getHtml($html);
	}

}
