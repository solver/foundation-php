<?php
namespace Solver\Services;

/**
 * Allows you to implement a fully dynamic endpoint, where you only define method resolve() and rely on this trait to 
 * route the resolved endpoints and actions as properties and methods, respectively, for native PHP callers. 
 * 
 * This trait also allows you to fetch an action as a closure, if you access it as a property instead of as a method,
 * however endpoints aren't accessible as methods, as they can't accept paramers.
 * 
 * NOTE: You can combine this trait with StaticEndpoint to provide hybrid static/dynamic resolution, see StaticEndpoint.
 * 
 * @method resolve($name) We declare the method pro forma to avoid IDE errors when we invoke it in the trait.
 */
trait DynamicEndpoint {
	public function __call($name, $args) {
		$method = $this->resolve($name);
		
		if (!$method instanceof \Closure) {
			throw new \Exception('No action with name "' . $name . '" found.');
		} else {
			return $method(...$args);
		}
	}
	
	public function __get($name) {
		$property = $this->resolve($name);
		
		if (!($property instanceof Endpoint || $property instanceof \Closure)) {
			throw new \Exception('No endpoint or action with name "' . $name . '" found.');
		} else {
			return $property;
		}
	}
}