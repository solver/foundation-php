<?php
namespace Solver\Services;

/**
 * A Null Object implementation of an endpoint with no properties and methods. This is useful with some proxy techniques
 * in order to keep the resolution chain (endpt->endpt->endpt->method()) going (as resolving to null interrupts chain
 * resolution).
 * 
 * An instance of this class is immutable & stateless, so it's a singleton obtained via static method get().
 * 
 * This can be considered an implementation of the null object pattern.
 */
class NullEndpoint implements Endpoint {
	/**
	 * Please use static method get() to obtain an instance.
	 */
	protected function __construct() {}
	
	public function resolve($name) { 
		return null;
	}
	
	public static function get() {
		static $i; return $i ?: $i = new self();
	}
}