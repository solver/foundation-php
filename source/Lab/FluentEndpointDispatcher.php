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
use Solver\Services\EndpointDispatcher2;
use Solver\Services\EndpointLog;
use Solver\Services\EndpointException;

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
class FluentEndpointDispatcher {
	protected $dispatcher;
	
	public function __construct() {
		$this->dispatcher = new EndpointDispatcher2();
	}
	
	/**
	 * Invokes the specified leaf action (by routing through every branch endpoint and action) and returns the results.
	 * 
	 * @param \Solver\Services\Endpoint $endpoint
	 * Endpoint instance to begin the routing from.
	 * 
	 * @param string|array $url
	 * string|list<string>; Fluent URL (just path, do not pass schema, host, query).
	 * 
	 * Clarification on the expected formats. It should be one of:
	 * 
	 * - As a string: "/foo;a=10/bar;b=20/".
	 * - As a hybrid list of strings, either just a name, or a name with inline params: ["foo;a=10", "bar;b=20/"].
	 * 
	 * @param array|null $queryFields
	 * dict|null = null; Fields from the URL query. Note that container structures (dicts, lists) aren't supported in query 
	 * fields passed here (PHP unfortunately percent decodes characters making it impossible to differentiate delimiters 
	 * from delimiter literals). If you need to pass structured input, pass it as fluent params in the last path segment
	 * above.
	 * 
	 * @param array|null $bodyFields
	 * dict|null = null; Fields from the request body.
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
	 * If the leaf action throws an EndpointException.
	 * 
	 * @throws \Solver\Services\EndpointException
	 * Code 'dispatcher.badRequest', if there are multiple given parameter sources for the leaf (fluent, query, body).
	 * 
	 * @throws \Solver\Services\EndpointException
	 * If the input contradicts any of the rules specified in \Solver\Services\EndpointDispatcher2::dispatch.
	 */
	public function dispatch(Endpoint $endpoint, $url, $queryFields = null, $bodyFields = null, \Closure $paramFilter = null) {
		// This is a pointless roundtrip from array to string, and the codec goes back to array, but we'll encapsulate
		// here for now, until the codec exposes an option for it cleanly.
		if (is_array($url)) $url = '/' . implode('/', $url) . '/';
		
		// TODO: Narrow the Exception type down to a specific exception type, when FluentUrlCodec throws a more 
		// specific Exception type.
		try {
			$chain = FluentUrlCodec::decode($url);	
		} catch (\Exception $e) { 
			$this->errorInvalidFluentUrl([], $e->getMessage());
		}
		
		// We can't perform this logic on an empty list, so we skip it. An empty chain would always throw 
		// "dispatcher.notFound" later anyway.
		if ($chain)  {
			$leafIndex = count($chain) - 1;
			if (is_array($chain[$leafIndex])) {
				if ($bodyFields || $queryFields) {
					$this->errorConflictingInputs($chain);
				}
			}
			
			elseif ($bodyFields) {
				if ($queryFields) {
					$this->errorConflictingInputs($chain);
				}
				
				$chain[$leafIndex] = ['name' => $chain[$leafIndex], 'params' => $bodyFields];
			}
			
			elseif ($queryFields) {
				$chain[$leafIndex] = ['name' => $chain[$leafIndex], 'params' => $queryFields];
			}
		}
		
		return $this->dispatcher->dispatch($endpoint, $chain, $paramFilter);
	}
	
	protected function getRouteString($chain) {
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
	
	protected function errorInvalidFluentUrl($chain, $message) {
		(new EndpointLog())->errorAndThrow(
			$this->getRouteString($chain), 
			'The request URL format is invalid.' . ($message ? ' ' . $message : ''),
			'dispatcher.badRequest');
	}
	
	protected function errorConflictingInputs($chain) {
		(new EndpointLog())->errorAndThrow(
			$this->getRouteString($chain), 
			'The request contains conflicting leaf parameter sources.',
			'dispatcher.badRequest');
	}
}