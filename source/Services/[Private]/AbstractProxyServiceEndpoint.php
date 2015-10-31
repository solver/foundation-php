<?php
namespace Solver\Services;

/**
 * DO NOT use this trait, it's an internal implementation detail for the proxy classes in the library & might go away or
 * change without warning.
 */
trait AbstractProxyEndpoint {
	use MembersViaResolve;
	
	/** @var \Solver\Services\Endpoint */
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
}