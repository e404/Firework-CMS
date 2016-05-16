<?php

/**
 * Model for classes that cannot be instantiable.
 *
 * When trying to instantiate, a fatal error will be thrown.
 */
abstract class NonInstantiable {

	/** @internal */
	final public function __construct() {
		Error::fatal('Trying to instantiate a non-instantiable class.');
	}

}
