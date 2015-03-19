<?php
namespace Solver\Lab;

/**
 * DO NOT use this trait, it's an internal implementation detail for the proxy classes in the library & might go away or
 * change without warning.
 */
trait AbstractProxyServiceEndpoint {
	/** @var \Solver\Lab\ServiceEndpoint */
	protected $endpoint;
	
	/** @var callable */
	protected $resolver;
	
	/** @var callable */
	protected $filter;
	
	public function resolve($name) {
		if ($this->resolver) {
			$resolver = $this->resolver;
			list($resolved, $resolution) = $resolver($name);
			if ($resolved) return $resolution;
		}
		
		if ($this->endpoint) $resolution = $this->endpoint->resolve($name);
		else $resolution = null;
		
		if ($this->filter) {
			$filter = $this->filter;
			$resolution = $filter($name, $resolution);
		}
		
		return $resolution;
	}
	
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
		
		if (!$property instanceof ServiceEndpoint) {
			throw new \Exception('Non-existing property "' . $name . '".');
		} else {
			return $property;
		}
	}
}