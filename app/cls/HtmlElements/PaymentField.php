<?php

/**
 * Form Field Hidden payment field.
 *
 * ***TODO:*** This field has to be implemented within the project and **must be removed here**.
 *
 * @deprecated This is project specific.
 */
class PaymentField extends HiddenField {

	private static $loadingText = 'Loading...';

	public static function setLoadingText($str) {
		self::$loadingText = $str;
	}

	public function init() {
		parent::init();
		$this->setName('payment_method_nonce');
	}

	public function getHtml() {
		return '<div id="payment-form"></div><div class="loading">'.self::$loadingText.'</div>';
	}

}
