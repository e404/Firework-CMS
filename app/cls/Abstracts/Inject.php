<?php

require_once(rtrim(__DIR__,'/').'/InjectStatic.php');

trait Inject {

	use InjectStatic;

	protected static $injectedMethods = [];

	public static function injectMethod($methodName, Closure $methodFunction) {
		$reflection = new ReflectionFunction($methodFunction);
		$params = $reflection->getParameters();
		if(!$params || $params[0]->getName()!=='This') {
			Error::fatal('Cannot inject method "'.$methodName.'": First parameter has to be $This');
		}
		self::$injectedMethods[strtoupper($methodName)] = $methodFunction;
	}

	public function __call($name, $arguments) {
		$name_uc = strtoupper($name);
		if(isset(self::$injectedMethods[$name_uc])) {
			array_unshift($arguments, $this);
			return call_user_func_array(self::$injectedMethods[$name_uc], $arguments);
		}else{
			Error::fatal('Class method not found: '.$name.'()');
			return null;
		}
	}

}
