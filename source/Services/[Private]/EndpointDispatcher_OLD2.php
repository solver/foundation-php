<?php
namespace Solver\Services;

use Solver\Logging\StatusLog;
use Solver\Accord\ActionException;
use Solver\Accord\Action;
use Solver\AccordX\ExpressLog;

/**
 * A simple router that will traverse service endpoints and either return a closure of the method to call, or null if
 * there's no method at the endpoint chain for that route.
 * 
 * For ex. $endpoint = "foo/bar/baz" will attempt to resolve to public $endpoint->foo->bar->baz($input);
 * 
 * TODO: Document more carefully we support actions returning endpoints as well now. Document the format of the $route.
 */
class EndpointDispatcher_OLD2 {	
	/**
	 * Invokes the specified leaf action (by routing through every branch endpoint and action) and returns the results.
	 * 
	 * @param \Solver\Services\Endpoint $endpoint
	 * Endpoint instance to begin the resolution from.
	 * 
	 * @param array $chain
	 * list<string | dict {name: string; params: dict}>; List of string segments to resolve to an action (for example a
	 * URL split by path separator), where optionally you may pass parameters to every segment by replacing the string 
	 * with a dictionary as the type specifies. Typically you will have input only on the last segment, the "leaf", 
	 * which must resolve to an action. But you can pass parameters to other segments, as long as they are actions that
	 * return endpoints.
	 * 
	 * See FluentUrlCodec for a possible way to encode a chain as a URL for GET requests (also if users prefer, for 
	 * POST request, in which cae typically the last segment's parameters will be specified via entity fields in a POST
	 * request).
	 *  
	 * @param \Closure|null $paramFilter
	 * ((params: dict, segmentIndex: int) => dict) | null = null; 
	 * 
	 * An optional mapping function that will be invoked to filter and transform the parameters for every path segment
	 * in the URL which resolved to an action.
	 * 
	 * You can throw an EndpointException from this method to abort processing the chain (throwing another kind of
	 * exception will be passed through up the chain).
	 * 
	 * @return array|null
	 * Returns the result from the invoked leaf action (an array or null).
	 * 
	 * @throws \Solver\Services\EndpointException
	 * If the method throws a EndpointException.
	 * 
	 * @throws \Solver\Services\EndpointException
	 * - Code "dispatcher.segmentNotFound", if a segment in the route is not found or the chain is empty.
	 * - Code "dispatcher.endpointResultExpected", if a branch action returns a result that's not an endpoint.
	 * - Code "dispatcher.dataResultExpected", if a leaf action returns a result that's not data.
	 * - Code "dispatcher.actionExpected", if the caller is passing parameters to a non-action branch, or the leaf is 
	 * not an action.
	 * - Code "dispatcher.segmentFailed", if a branch or a leaf returned errors when invoked (the exact errors
	 * immediately precede this error in the log).
	 * - Code "dispatcher.paramFilterFailed", if the caller-supplied parameter filter throws an EndpointException.
	 */
	public function dispatch(Endpoint $endpoint, $chain, \Closure $paramFilter = null, StatusLog $log = null) {
		// TODO: Not use this log here.
		$log = new ExpressLog($log);
		
		$maxI = count($chain) - 1;
		
		if ($maxI == -1) $this->segmentNotFound($log, $chain, -1);
		
		for ($i = 0; $i <= $maxI; $i++) {
			$requestSeg = $chain[$i];
			
			if (is_string($requestSeg)) {
				$requestSegName = $requestSeg;
				$requestSegParams = null;
			} else {
				$requestSegName = $requestSeg['name'];
				$requestSegParams = $requestSeg['params'];
			}
			
			$responseSeg = $endpoint->resolve($requestSegName);	
			
			if ($responseSeg === null) {
				$this->segmentNotFound($log, $chain, $i);
			}
			
			if ($responseSeg instanceof Action) {
				try {
					if ($paramFilter) $requestSegParams = $paramFilter($requestSegParams, $i);
					$result = $responseSeg->apply($requestSegParams, $log->withPath([$i]));
				} catch (ActionException $e) {
					$this->segmentFailed($e->getLog(), $chain, $i, $maxI);
					throw $e;
				}
				
				if ($i == $maxI) {
					if ($result instanceof Endpoint) $this->dataResultExpected($log, $chain, $i, $maxI);
					return $result;
				} else {
					if (!$result instanceof Endpoint) $this->endpointResultExpected($log, $chain, $i, $maxI);
					$endpoint = $result;
				}
			} else {
				if ($i == $maxI || $requestSegParams) $this->actionExpected($log, $chain, $i, $maxI);
				$endpoint = $responseSeg;
			}
		}
	}
		
	protected function segmentNotFound(StatusLog $log, $chain, $errorAtIndex) {
		$log->addError(
			$this->getRoutePath($chain, $errorAtIndex),
			'Segment not found.', 
			'dispatcher.segmentNotFound');
	}
	
	protected function endpointResultExpected(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			$this->getRoutePath($chain, $errorAtIndex), 
			'Branch action returned data, endpoint expected.', 
			'dispatcher.endpointResultExpected');		
	}
	
	protected function dataResultExpected(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			$this->getRoutePath($chain, $errorAtIndex), 
			'Leaf action returned an endpoint, data expected.', 
			'dispatcher.dataResultExpected');		
	}
		
	protected function actionExpected(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			$this->getRoutePath($chain, $errorAtIndex), 
			$errorAtIndex == $maxIndex ? 'Leaf is not an action.' : 'Cannot pass parameters to a branch endpoint, action expected.', 
			'dispatcher.actionExpected');		
	}
	
	protected function segmentFailed(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			$this->getRoutePath($chain, $errorAtIndex), 
			$errorAtIndex == $maxIndex ? 'Leaf returned errors.' : 'Branch returned errors.', 
			'dispatcher.segmentFailed');		
	}
	
	protected function paramFilterFailed(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			$this->getRoutePath($chain, $errorAtIndex), 
			'Parameter filter returned errors.', 
			'dispatcher.paramFilterFailed');		
	}
	
	protected function getRoutePath($chain, $errorAtIndex) {
		$route = ['route'];
		
		for ($i = 0; $i <= $errorAtIndex; $i++) {
			$seg = $chain[$i];
			
			if (is_string($seg)) {
				$route[] = $seg;
			} else {
				$route[] = $seg['name'];
			}
		}
		
		return $route;
	}
}