<?php

trait InjectStatic {

	protected static $injectedStaticMethods = [];

	public static function injectStaticMethod($methodName, Closure $methodFunction) {
		$reflection = new ReflectionFunction($methodFunction);
		$params = $reflection->getParameters();
		if(!$params || $params[0]->getName()!=='Self') {
			Error::fatal('Cannot inject method "'.$methodName.'": First parameter has to be $Self');
		}
		self::$injectedStaticMethods[strtoupper($methodName)] = $methodFunction;
	}

	public static function __callStatic($name, $arguments) {
		$name_uc = strtoupper($name);
		if(isset(self::$injectedStaticMethods[$name_uc])) {
			array_unshift($arguments, get_called_class());
			return call_user_func_array(self::$injectedStaticMethods[$name_uc], $arguments);
		}else{
			Error::fatal('Class method not found: '.$name.'()');
			return null;
		}
	}

}
