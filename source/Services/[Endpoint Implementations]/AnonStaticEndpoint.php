<?php
namespace Solver\Services;

/**
 * Implements a static endpoint you can instantiate and define inline by passing an array of endpoints and closures (as
 * actions) to be returned when resolve() is invoked.
 */
class AnonStaticEndpoint implements Endpoint {
	use DynamicEndpoint; // Despite counter-intuitively using this trait, the name of the class is right.
	
	protected $members;

	public function __construct(array $members) {
		$this->members = $members;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Services\Endpoint::resolve()
	 */
	public function resolve($name) {
		$members = $this->members;
		return isset($members[$name]) ? $members[$name] : null;
	}
}