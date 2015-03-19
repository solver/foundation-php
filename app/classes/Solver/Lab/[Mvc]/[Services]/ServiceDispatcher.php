<?php
namespace Solver\Lab;

/**
 * A simple router that will traverse service endpoints and either return a closure of the method to call, or null if
 * there's no method at the endpoint chain for that route.
 * 
 * For ex. $endpoint = "foo/bar/baz" will attempt to resolve to public $endpoint->foo->bar->baz($input);
 */
class ServiceDispatcher {
	/**
	 * Tries to find a method for the given route and returns it. Returns null if no matching method is found.
	 * 
	 * @param \solver\Lab\ServiceEndpoint $endpoint
	 * ServiceEndpoint instance to begin the resolution from.
	 * 
	 * @param string $route
	 * String route to resolve to a method.
	 * 
	 * @param array|null $input
	 * Input to pass to the method.
	 * 
	 * @param string $separator
	 * Default "/". Which separator to use to split segments out of the given route.
	 * 
	 * @return array|null
	 * Returns the result from the invoked method (an array or null).
	 * 
	 * @throws \Solver\Lab\ServiceException
	 * If the method throws a ServiceException.
	 * 
	 * @throws \Solver\Lab\ServiceException
	 * With code 'endpointOrActionNotFound', if the route doesn't resolve to a method.
	 */
	public function dispatcher(ServiceEndpoint $endpoint, $route, array $input = null, $separator = '/') {
		$action = $this->route($endpoint, $route, $separator);
		
		if ($action === null) {
			$log = new ServiceLog();
			$log->addError(null, 'Endpoint or action not found.', 'endpointOrActionNotFound');
			$log->throwIfErrors(); 
		}
		
		return $action($input);
	}
	
	/**
	 * Tries to find a method for the given route and returns it. Returns null if no matching method is found.
	 * 
	 * @param \solver\Lab\ServiceEndpoint $endpoint
	 * @param string $route
	 * @param string $separator
	 * @return \Closure|null
	 */
	protected function route(ServiceEndpoint $endpoint, $route, $separator = '/') {		
		$routeSegs = explode($separator, trim($route, $separator));
		$count = count($routeSegs);
		
		if ($count == 0) return null;
		
		for ($i = 0; $i < $count; $i) {
			$endpoint = $endpoint->resolve($routeSegs[$i]);
			
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