<?php

trait InjectStatic {

	protected static $injectedStaticMethods = [];

	public static function injectStaticMethod($methodName, $methodFunction) {
		self::$injectedStaticMethods[strtoupper($methodName)] = $methodFunction;
	}

	public static function __callStatic($name, $arguments) {
		$name = strtoupper($name);
		if(isset(self::$injectedStaticMethods[$name])) {
			array_unshift($arguments, get_called_class());
			return call_user_func_array(self::$injectedStaticMethods[$name], $arguments);
		}else{
			Error::fatal('Class method not found: '.$name.'()');
			return null;
		}
	}

}
