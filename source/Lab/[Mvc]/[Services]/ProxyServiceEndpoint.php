<?php
namespace Solver\Lab;

/**
 * Allows you to intercept the resolution of methods and sub-endpoints on an endpoint.
 * 
 * This allows you to decorate / wrap / override endpoint methods and properties, and add cross-cutting functionality
 * that would be harder to implement by overriding every endpoint's method manually.
 */
class ProxyServiceEndpoint implements ServiceEndpoint {
	use AbstractProxyServiceEndpoint;
	
	/**
	 * #Resolution: \Solver\Lab\ServiceEndpoint|\Closure|null;
	 * 
	 * @param \Solver\Lab\ServiceEndpoint $endpoint
	 * The endpoint this object will decorate (i.e. wrap).
	 * 
	 * @param null|callable $resolver
	 * null | (name: string) => tuple {resolved: bool; resolution: #Resolution}
	 * 
	 * Invoked before the true endpoint's own resolver. If "resolved" is true, the accompanying resolution is used and
	 * things end there. If "resolved" is false, the name is passed to the true endpoint to resolve, and then your
	 * filter callable is invoked (if defined).
	 * 
	 * @param null|callable $filter
	 * null | (name: string, resolution: #Resolution) => #Resolution
	 * 
	 * Invoked after the true endpoint's own resolver. You receive the name being resolved & the true endpoint's
	 * resolution, and you have the chance to override it by returning a modified result. 
	 * 
	 * Keep in mind if you return nothing that's effectively the same as overriding the resolution to null. If you don't
	 * want to override the result, you must return the passed resolution value.
	 */
	public function __construct(ServiceEndpoint $endpoint, $resolver = null, $filter = null) {
		$this->endpoint = $endpoint;
		$this->resolver = $resolver;
		$this->filter = $filter;		
	}
}