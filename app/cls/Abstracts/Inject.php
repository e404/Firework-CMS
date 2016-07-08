<?php

trait Inject {

	protected static $injectedMethods = [];

	public static function injectMethod($methodName, $methodFunction) {
		self::$injectedMethods[strtoupper($methodName)] = $methodFunction;
	}

	public function __call($name, $arguments) {
		$name = strtoupper($name);
		if(isset(self::$injectedMethods[$name])) {
			return call_user_func_array(self::$injectedMethods[$name], $arguments);
		}else{
			return null;
		}
	}

}
