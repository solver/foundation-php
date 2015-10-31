<?php
/*
 * Copyright (C) 2011-2015 Solver Ltd. All rights reserved.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Lab;

use Solver\Services\Endpoint;
use Solver\Services\EndpointDispatcher;
use Solver\Accord\Action;
use Solver\Logging\StatusLog;
use Solver\Accord\ActionException;

/**
 * Dispatches an endpoint, from a fluent URL, query fields and entity body field requests.
 * 
 * This class builds upon:
 * 
 * - \Solver\Services\EndpointDispatcher2
 * - \Solver\Lab\FluentUrlCodec
 * 
 * Keep in mind some rules about for resolving conflicts between Fluent, Query ($_GET) and Entity body ($_POST) params.
 * 
 * - You can't have two of those at once: fluent fields on the leaf path segment; query fields; body fields.
 * - Prefer fluent over query, we support query only to support clients (like GET browser forms) who only support query.
 * - If the request method allows sending a body, prefer body fields over fluent or query for the leaf parameters.
 * - If the fluent URL is too long to encode without entity fields, this resource can't be queried over GET, you need to
 * fall back to POST or PATCH and encode the leaf parameters via body fields.
 * - If you pass the leaf parameters via the body, and the URL is still too long, you need to have an endpoint action 
 * that can take the full fluent route encoded as a JSON array in the body, and route it itself to the right leaf 
 * action. However it's a sign of a very bad API design if this is needed. Branch params should be kept *short*.
 */
class FluentEndpointDispatcher implements Action {
	protected $dispatcher;
	
	/**
	 * @param \Solver\Services\Endpoint $endpoint
	 * Endpoint instance to begin the routing from.
	 * 
	 * @param \Closure|null $paramFilter
	 * (#Filter | null) = null; 
	 * 
	 * #Filter: ($path, params: dict, StatusLog $log = null) => dict;
	 * 
	 * An optional mapping function that will be invoked to filter and transform the parameters for every path segment
	 * in the URL which resolved to an action.
	 * 
	 * You can throw an ActionException from this method to abort processing the chain (throwing another kind of
	 * exception will be passed through up the chain). If you log events, you should log at the path given (you can
	 * append further segments to it). The last segment is the action index in the chain.
	 */
	public function __construct(Endpoint $endpoint, \Closure $paramFilter = null) {
		$this->dispatcher = new EndpointDispatcher($endpoint, $paramFilter);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\Action::apply()
	 */
	public function apply($input = null, \Solver\Logging\StatusLog $log = null) {
		// FIXME: We shouldn't fail with a hard error when a param is missing, but ActionException.
		return $this->dispatch(
			isset($input['url']) ? $input['url'] : null,
			isset($input['query']) ? $input['query'] : null,
			isset($input['body']) ? $input['body'] : null,
			$log
		);
	}
	
	/**
	 * Invokes the specified leaf action (by routing through every branch endpoint and action) and returns the results.
	 * 
	 * This is an alternative calling convention for apply() and has the same semantics, but a different argument
	 * format.
	 * 
	 * @param string|array $url
	 * string|list<string>; Fluent URL (just path, do not pass schema, host, query).
	 * 
	 * Clarification on the expected formats. It should be one of:
	 * 
	 * - As a string: "/foo;a=10/bar;b=20/".
	 * - As a hybrid list of strings, either just a name, or a name with inline params: ["foo;a=10", "bar;b=20/"].
	 * 
	 * @param array|null $query
	 * dict|null = null; Fields from the URL query. Note that container structures (dicts, lists) aren't supported in query 
	 * fields passed here (PHP unfortunately percent decodes characters making it impossible to differentiate delimiters 
	 * from delimiter literals). If you need to pass structured input, pass it as fluent params in the last path segment
	 * above.
	 * 
	 * @param array|null $body
	 * dict|null = null; Fields from the request body.
	 * 
	 * @param \Solver\Logging\StatusLog $log
	 * Log to add events to.
	 * 
	 * @return array|null
	 * Returns the result from the invoked leaf action (an array or null).
	 * 
	 * @throws \Solver\Accord\ActionException
	 * If the leaf action throws an EndpointException.
	 * 
	 * @throws \Solver\Accord\ActionException
	 * Code 'dispatcher.badRequest', if there are multiple given parameter sources for the leaf (fluent, query, body).
	 * 
	 * @throws \Solver\Accord\ActionException
	 * If the input contradicts any of the rules specified in \Solver\Services\EndpointDispatcher2::dispatch.
	 */
	public function dispatch($url, $query = null, $body = null, StatusLog $log = null) {
		// This is a pointless roundtrip from array to string, and the codec goes back to array, but we'll encapsulate
		// here for now, until the codec exposes an option for it cleanly.
		if (is_array($url)) $url = '/' . implode('/', $url) . '/';
		
		// TODO: Narrow the Exception type down to a specific exception type, when FluentUrlCodec throws a more 
		// specific Exception type.
		try {
			$chain = FluentUrlCodec::decode($url);	
		} catch (\Exception $e) { 
			$this->errorInvalidFluentUrl([], $e->getMessage(), $log);
		}
		
		// We can't perform this logic on an empty list, so we skip it. An empty chain would always throw 
		// "dispatcher.notFound" later anyway.
		if ($chain)  {
			$leafIndex = count($chain) - 1;
			if (is_array($chain[$leafIndex])) {
				if ($body || $query) {
					$this->errorConflictingInputs($chain, $log);
				}
			}
			
			elseif ($body) {
				if ($query) {
					$this->errorConflictingInputs($chain, $log);
				}
				
				$chain[$leafIndex] = ['name' => $chain[$leafIndex], 'params' => $body];
			}
			
			elseif ($query) {
				$chain[$leafIndex] = ['name' => $chain[$leafIndex], 'params' => $query];
			}
		}
		
		return $this->dispatcher->dispatch($chain, $log);
	}
	
	protected function getRouteString($chain, StatusLog $log = null) {
		$segs = ['route'];
		
		foreach ($chain as $seg) {
			if (is_string($seg)) {
				$segs[] = $seg;
			} else {
				$segs[] = $seg['name'];
			}
		}
		
		return implode('.', $segs);
	}
	
	protected function errorInvalidFluentUrl($chain, $message, StatusLog $log = null) {
		if ($log) $log->addError(
			$this->getRouteString($chain), 
			'The request URL format is invalid.' . ($message ? ' ' . $message : ''),
			'dispatcher.badRequest');
		throw new ActionException($log);
	}
	
	protected function errorConflictingInputs($chain, StatusLog $log = null) {
		if ($log) $log->addError(
			$this->getRouteString($chain), 
			'The request contains conflicting leaf parameter sources.',
			'dispatcher.badRequest');
		throw new ActionException($log);
	}
}