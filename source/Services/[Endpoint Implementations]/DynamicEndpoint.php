<?php
namespace Solver\Services;

/**
 * Allows you to implement a fully dynamic endpoint, by only defining method resolve() and relying on this trait to 
 * route the resolved endpoints and actions as properties and methods, respectively, for native PHP callers. 
 * 
 * @method resolve($name) We declare the method pro forma to avoid IDE errors when we invoke it in the trait.
 */
trait DynamicEndpoint {
	public function __call($name, $args) {
		$method = $this->resolve($name);
		
		if (!$method instanceof \Closure) {
			throw new \Exception('Non-existing method "' . $name . '".');
		} else {
			return $method(...$args);
		}
	}
	
	public function __get($name) {
		$property = $this->resolve($name);
		
		if (!$property instanceof Endpoint) {
			throw new \Exception('Non-existing property "' . $name . '".');
		} else {
			return $property;
		}
	}
}