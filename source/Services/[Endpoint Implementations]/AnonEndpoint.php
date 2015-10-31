<?php
namespace Solver\Services;

use Solver\Accord\AnonAction;
/**
 * Implements a static endpoint you can instantiate and define inline by passing an array of endpoints and closures (as
 * actions) to be returned when resolve() is invoked.
 * 
 * Alternatively you can pass in a single Closure to stand in for the resolve method, so you can resolve dynamically.
 */
class AnonEndpoint implements Endpoint {
	use MembersViaResolve;
	
	protected $resolve;

	/**
	 * @param \Closure|array $resolve
	 * A closure as an implementation of resolve(), or an array of static resolutions $name => $resolution. The 
	 * resolution value should be either an Endpoint instance, a Closure (as an Action) or an Action implementation.
	 * 
	 * If you pass a closure, you can also return a Closure instead of an Action, and this class will automatically
	 * convert it to an Action for you.
	 */
	public function __construct($resolve) {
		$this->resolve = $resolve;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Services\Endpoint::resolve()
	 */
	public function resolve($name) {
		$resolve = $this->resolve;
		
		if ($resolve instanceof \Closure) $resolution = $resolve($name);
		else $resolution = isset($resolve[$name]) ? $resolve[$name] : null;
		
		if ($resolution === null) return null;		
		if ($resolution instanceof \Closure) return new AnonAction($resolution);
		return $resolution;
	}
}