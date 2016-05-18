<?php

/**
 * Extending classes must be instanced.
 */
abstract class Instantiable {

	/**
	 * Returns a new instance of the current class.
	 * 
	 * @access public
	 * @static
	 */
	public static function newInstance() {
		$reflect = new ReflectionClass(get_called_class());
		return $reflect->newInstanceArgs(func_get_args());
	}

}
