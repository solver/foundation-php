<?php
namespace Solver\Lab;

/**
 * A simple router that will traverse service endpoints and either return a closure of the method to call, or null if
 * there's no method at the endpoint chain for that route.
 * 
 * For ex. $endpoint = "foo/bar/baz" will attempt to resolve to public $endpoint->foo->bar->baz($input);
 */
class ServiceEndpointDispatcher {
	/**
	 * Tries to find a method for the given route and returns it. Returns null if no matching method is found.
	 * 
	 * @param \solver\Lab\ServiceEndpoint $endpoint
	 * ServiceEndpoint instance to begin the resolution from.
	 * 
	 * @param array $route
	 * list<string>; List of string segments to resolve to an action (for example a URL split by path separator).
	 * 
	 * @param array|null $input
	 * Input to pass to the method.
	 * 
	 * @return array|null
	 * Returns the result from the invoked method (an array or null).
	 * 
	 * @throws \Solver\Lab\ServiceEndpointException
	 * If the method throws a ServiceEndpointException.
	 * 
	 * @throws \Solver\Lab\ServiceEndpointException
	 * With code 'endpointOrActionNotFound', if the route doesn't resolve to a method.
	 */
	public function dispatch(ServiceEndpoint $endpoint, $route, array $input = null) {
		$action = $this->route($endpoint, $route);
		
		if ($action === null) {
			$log = new ServiceEndpointLog();
			$log->error(null, 'Endpoint or action not found.', 'endpointOrActionNotFound');
			$log->throwIfErrors(); 
		}
		
		return $action($input);
	}
	
	/**
	 * Tries to find a method for the given route and returns it. Returns null if no matching method is found.
	 * 
	 * @param \solver\Lab\ServiceEndpoint $endpoint
	 * @param string $route
	 * @return \Closure|null
	 */
	protected function route(ServiceEndpoint $endpoint, $route) {	
		$count = count($route);
		
		if ($count == 0) return null;
		
		for ($i = 0; $i < $count; $i++) {
			$endpoint = $endpoint->resolve($route[$i]);
			
			if ($endpoint === null) return null;
			
			if ($endpoint instanceof \Closure) {
				if ($i == $count - 1) {
					return $endpoint;
				} else {
					return null;
				}
			}
		}
	}
}