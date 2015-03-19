<?php
namespace Solver\Lab;

/**
 * An endpoint with no properties and methods. This is useful with some proxy techniques in order to keep the 
 * resolution chain (endpt->endpt->endpt->method()) going (as resolving to null interrupts chain resolution).
 * 
 * An instance of this class is immutable & stateless, so it's a singleton obtained via static method get().
 */
class EmptyServiceEndpoint implements ServiceEndpoint {
	public function resolve($name) { 
		return null;
	}
	
	public static function get() {
		static $i; return $i ?: $i = new self();
	}
}