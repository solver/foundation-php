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

/**
 * Ties the router & page controller together, invokes an optional fallback handler in case of exceptions.
 */
class Dispatcher {
	/**
	 * @param callable $router
	 * Router callable. Any callable would do. Takes input, should return one of the following messages (codes after 
	 * relevant HTTP statuses):
	 * 
	 * - [200, $pageCallable, $pageInput]
	 * - [301, $url]
	 * - [404]
	 * 
	 * 
	 * @param callable|string $fallback
	 * Optional. Either any callable or a string class name of a callable class to invoke. Called when a controller 
	 * throws an exception, or the router can't resolve to a controller (404).
	 * 
	 * If you don't specify a fallback, or if the fallback itself throws an exception, that exception will be thrown 
	 * out and left to the dispatcher client to handle.
	 */
	public function __construct(callable $router, $fallback = null) {
		$this->router = $router;
		$this->fallback = $fallback;
	}
	
	/**
	 * Dispatches the controller selected by the provided router with the given input.
	 * 
	 * @param array $input
	 */
	public function dispatch($input) {
		$router = $this->router;
		$message = $router($input);
		
		$code = $message[0];
		
		switch ($code) {
			case 200:
				list(, $pageCallable, $pageInput) = $message;
				
				// TODO: (Tentative) the exception should be contained within the page, it should be caught and return a
				// message instead (like router's 404 etc.)?
				try {
					$pageCallable($pageInput);
				} catch (\Exception $e) {
					if ($this->fallback) {
						$input['exception'] = $e;
						$this->dispatchFallback($input);
						return;
					} else {
						throw $e;
					}
				}
				break;
				
			case 301:
				list(, $url) = $result;
				\header('HTTP/1.1 301 Moved Permanently');
				\header('Location: ' . $url);
				break;
				
			case 404:
				$notFound = new PageException(null, 404);
				
				if ($this->fallback) {
					$input['exception'] = $notFound;
					$this->dispatchFallback($input);
					return;
				} else {
					throw $notFound;
				}
				break;
				
			default:
				throw new \Exception('Unexpected router message format.');
		}
	}
	
	protected function dispatchFallback($input) {
		if (is_string($this->fallback)) $this->fallback = new $this->fallback;
		$fallback = $this->fallback;
		return $fallback($input);
	}
}