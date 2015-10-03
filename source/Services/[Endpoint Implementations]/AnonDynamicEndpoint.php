<?php
namespace Solver\Services;

/**
 * Implements a dynamic endpoint you can instantiate and define inline by passing a closure to implement method
 * resolve().
 */
class AnonDynamicEndpoint implements Endpoint {
	use DynamicEndpoint;
	
	protected $resolve;

	public function __construct(\Closure $resolve) {
		$this->resolve = $resolve;
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Services\Endpoint::resolve()
	 */
	public function resolve($name) {
		return $this->resolve->__invoke($name);
	}
}