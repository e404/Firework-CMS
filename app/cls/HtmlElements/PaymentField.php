<?php

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
