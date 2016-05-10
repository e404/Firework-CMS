<?php

require_once('lib/braintree-php-3.8.0/lib/Braintree.php');

class PaymentProcess {

	protected $error = null;
	public static $configured = false;

	public static function init() {
		if(self::$configured) return;
		if(App::isSandboxed()) {
			Braintree_Configuration::environment(Config::get('payment_sandbox', 'environment'));
			Braintree_Configuration::merchantId(Config::get('payment_sandbox', 'merchant_id'));
			Braintree_Configuration::publicKey(Config::get('payment_sandbox', 'public_key'));
			Braintree_Configuration::privateKey(Config::get('payment_sandbox', 'private_key'));
		}else{
			Braintree_Configuration::environment(Config::get('payment', 'environment'));
			Braintree_Configuration::merchantId(Config::get('payment', 'merchant_id'));
			Braintree_Configuration::publicKey(Config::get('payment', 'public_key'));
			Braintree_Configuration::privateKey(Config::get('payment', 'private_key'));
		}
		self::$configured = true;
	}

	public static function ajax() {
		self::init();
		return Braintree_ClientToken::generate();
	}

	public function __construct() {
		self::init();
	}

	public function transaction($amount) {
		$result = Braintree_Transaction::sale(array(
			'paymentMethodNonce' => $_POST['payment_method_nonce'],
			'amount' => $amount,
			'options' => [
				'submitForSettlement' => true
			]
		));
		$this->error = $result->success ? null : $result->message;
		return $result->success;
	}

	public function getError() {
		return $this->error;
	}

}
