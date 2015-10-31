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
class EndpointDispatcher implements Action {
	protected $endpoint, $paramFilter;
	
	/**
	 * @param \Solver\Services\Endpoint $endpoint
	 * Endpoint instance to begin the resolution from.
	 *  
	 * @param \Closure|null $paramFilter
	 * (#Filter | null) = null; 
	 * 
	 * #Filter: (params: dict, path: list<string|int>, log: StatusLog = null) => dict;
	 * 
	 * An optional mapping function that will be invoked to filter and transform the parameters for every path segment
	 * in the URL which resolved to an action.
	 * 
	 * You can throw an ActionException from this method to abort processing the chain (throwing another kind of
	 * exception will be passed through up the chain). If you log events, you should log at the path given (you can
	 * append further segments to it). The last segment is the action index in the chain.
	 */
	public function __construct(Endpoint $endpoint, \Closure $paramFilter = null) {
		$this->endpoint = $endpoint;
		$this->paramFilter = $paramFilter;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\Action::apply()
	 */
	public function apply($input = null, \Solver\Logging\StatusLog $log = null) {
		// FIXME: We shouldn't fail with a hard error when chain is missing, but ActionException.
		return $this->dispatch(isset($input['chain']) ? $input['chain'] : null, $log);
	}

	/**
	 * Invokes the specified leaf action (by routing through every branch endpoint and action) and returns the results.
	 * 
	 * This method has semantics that match Action's apply method, and reports problems with path ["chain", ...] when
	 * a link in the chain fails to resolve or fails when invoked (as if the passed input is a dict with key "chain").
	 * 
	 * It's an alternative calling convention for apply().
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
	 * @return array|null
	 * Returns the result from the invoked leaf action (an array or null).
	 * 
	 * @throws \Solver\Services\EndpointException
	 * If the method throws a EndpointException.
	 * 
	 * @throws \Solver\Accord\ActionException
	 * - Code "dispatcher.segmentNotFound", if a segment in the route is not found or the chain is empty.
	 * - Code "dispatcher.endpointResultExpected", if a branch action returns a result that's not an endpoint.
	 * - Code "dispatcher.dataResultExpected", if a leaf action returns a result that's not data.
	 * - Code "dispatcher.actionExpected", if the caller is passing parameters to a non-action branch, or the leaf is 
	 * not an action.
	 * - If an action ends with a failure, its errors get logged and the dispatcher fails as well.
	 */
	public function dispatch(array $chain, StatusLog $log = null) {
		$endpoint = $this->endpoint;
		$paramFilter = $this->paramFilter;
		
		// TODO: Not use this log here. We need it for method at() for now.
		$callerLog = $log;
		$log = new ExpressLog($callerLog);
		
		// Remove this try block once we don't wrap the log in ExpressLog (so we don't need to unwrap it in catch).
		try {		
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
					if ($paramFilter) $requestSegParams = $paramFilter($requestSegParams, ['chain', $i], $log);
					// TODO: Support FastAction (if so, we should throw manually on failure for that one).
					$result = $responseSeg->apply($requestSegParams, $log->at(['chain', $i]));
					
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
		} catch (ActionException $e) {
			throw new ActionException($callerLog, $e);
		}
	}
		
	protected function segmentNotFound(StatusLog $log, $chain, $errorAtIndex) {
		$log->addError(
			['chain', $errorAtIndex],
			'Segment not found.', 
			'dispatcher.segmentNotFound');
		throw new ActionException($log);
	}
	
	protected function endpointResultExpected(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			['chain', $errorAtIndex], 
			'Branch action returned data, endpoint expected.', 
			'dispatcher.endpointResultExpected');	
		throw new ActionException($log);	
	}
	
	protected function dataResultExpected(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			['chain', $errorAtIndex], 
			'Leaf action returned an endpoint, data expected.', 
			'dispatcher.dataResultExpected');	
		throw new ActionException($log);	
	}
		
	protected function actionExpected(StatusLog $log, $chain, $errorAtIndex, $maxIndex) {
		$log->addError(
			['chain', $errorAtIndex], 
			$errorAtIndex == $maxIndex ? 'Leaf is not an action.' : 'Cannot pass parameters to a branch endpoint, action expected.', 
			'dispatcher.actionExpected');		
		throw new ActionException($log);
	}
}