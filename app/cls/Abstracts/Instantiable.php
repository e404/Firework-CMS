<?php

abstract class Instantiable {

	public static function newInstance() {
		$reflect = new ReflectionClass(get_called_class());
		return $reflect->newInstanceArgs(func_get_args());
	}

}
