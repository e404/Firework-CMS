<?php

/**
 * Model for classes that cannot be instantiable.
 */
abstract class NonInstantiable {

	/**
	 * Throws a fatal error.
	 * 
	 * @access public
	 * @final
	 * @return void
	 */
	final public function __construct() {
		Error::fatal('Trying to instantiate a non-instantiable class.');
	}

}
