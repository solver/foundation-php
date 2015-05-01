<?php
namespace Solver\Services;

/**
 * A variation of EndpointProxy {@see \Solver\Services\EndpointProxy}.
 * 
 * This implementation will automatically proxy endpoints deeper into the given root endpoints, so you can cover a whole
 * tree of endpoints with one proxy configuration.
 * 
 * In EndpointProxy, when you invoke root->foo->bar->baz(); and "root" is a proxy, your callbacks will only be
 * invoked with $name "foo". 
 * 
 * But with this class, your callbacks will be invoked three times with $path set to, in order:
 * 
 * - ["foo"]
 * - ["foo", "bar"]
 * - ["foo", "bar", "baz"]
 * 
 * This allows less work when implementing cross-cutting concerns accross deeply nested endpoints, by being able to
 * cover them easily from one set of proxy callbacks. 
 * 
 * Note that the chain execution interrupts if both your $resolver and the $endpoint you pass resolve to null or a
 * method (i.e. a Closure instance). To avoid that, your $filter function can convert non-endpoint resolutions to an 
 * instance of EmptyEndpoint.
 * 
 */
class DeepProxyEndpoint implements Endpoint {
	use AbstractProxyEndpoint;
	
	/** @var array */
	protected $path = [];
	
	/**
	 * #Resolution: \Solver\Services\Endpoint|\Closure|null;
	 * 
	 * @param \Solver\Services\Endpoint $endpoint
	 * The endpoint this object will decorate (i.e. wrap).
	 * 
	 * @param null|callable $resolver
	 * null | (path: string[]) => tuple {resolved: bool; resolution: #Resolution}
	 * 
	 * Invoked before the true endpoint's own resolver. If "resolved" is true, the accompanying resolution is used and
	 * things end there (at this path depth). If "resolved" is false, the path is passed to the true endpoint to
	 * resolve, and then your filter callable is invoked (if defined).
	 * 
	 * @param null|callable $filter
	 * null | (path: string[], resolution: #Resolution) => #Resolution
	 * 
	 * Invoked after the true endpoint's own resolver. You receive the path being resolved & the true endpoint's
	 * resolution, and you have the chance to override it by returning a modified result. 
	 * 
	 * Keep in mind if you return nothing that's effectively the same as overriding the resolution to null. If you don't
	 * want to override the result, you must return the passed resolution value.
	 */
	public function __construct(Endpoint $endpoint, $resolver = null, $filter = null) {
		$this->endpoint = $endpoint;
		
		$this->resolver = function ($name) use ($endpoint, $resolver, $filter) {
			if ($resolver) {
				$path = $this->path;
				$path[] = $name;
				
				list($resolved, $resolution) = $resolver($path);
				
				if ($resolved) {
					if ($resolution !== null) {
						$resolution = new self($endpoint, $resolver, $filter);
						$resolution->path = $path;
					}
					
					return [true, $resolution]; 
				} 
			}
			
			return [false, null];
		};
		
		$this->filter = function ($name, $resolution) use ($endpoint, $resolver, $filter) {
			if ($filter) {
				$path = $this->path;
				$path[] = $name;
				
				$resolution = $filter($path, $resolution);
				
				if ($resolution !== null) {
					$resolution = new self($endpoint, $resolver, $filter);
					$resolution->path = $path;
				}
				
				return $resolution; 
			}
		};
	}
}