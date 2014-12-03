<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
 * A barebones router.
 */
class Router {
	/**
	 * @var array
	 */
	private $config;
	
	/**
	 * @param array $config
	 * A dict with keys:
	 * 
	 * # bool preferTrailingSlash
	 * True if you want to append slashes to URLs, unless they appear to have a file extension. False if you want to 
	 * remove a trailing slash. In both cases there's one canonical version for every URL with and without a slash.
	 * 
	 * # list $routes
	 * 
	 * A list of routes (from least to most specific, last match is used). Each route is a dict with the following
	 * properties:
	 * 
	 * <code>
	 * ['path' => '/URL/to/match', 'call' => 'Controller\Class' or function ($input, $router) {...} ]
	 * </code>
	 * 
	 * Additional optional keys you may add:
	 * 
	 * - "tail" (boolean, false by default). If enabled, the router will parse path segments after the route as
	 * parameters (and puts them in $input['t']).
	 * 
	 * - "tailLength" (int, null by default). If specified, sets a limit on the exact length the tail can be for this 
	 * route to match (i.e. how many path segments). By default there's no limit.
	 * 
	 * - "tailLengthMax" (int, null by default). If specified, sets a limit on how long the tail can be for this route
	 * to match (i.e. how many path segments). By default there's no limit.
	 * 
	 * - "tailLengthMin" (int, null by default). If specified, sets a limit on how short the tail can be for this route
	 * to match (i.e. how many path segments). By default there's no limit.
	 * 
	 * - "vars" (mixed). Typically, but necessarily a dict. If present, it'll be added to $input under key "v". You can
	 * use this feature to pass custom data to controllers.
	 * 
	 * # list $errors
	 * A list of HTTP status handlers (401, 404, 500 etc.). Operate similar to routes, but instead of a key "path" they
	 * have a key "status":
	 * 
	 * <code>
	 * ['status' => 123, 'call' => 'Controller\Class' or function ($input, $router) {...} ]
	 * </code>
	 * 
	 * Like routes, you can specify "vars" for the handler, but key "tail" is not applicable here.
	 * 
	 * # list $head
	 * Reserved for future use (for use with multi-lingual sites ex. foo.com/en-us/page). Don't pass this yet.
	 */
	public function __construct(array $config) {
		ParamValidator::validate('config', $config, [
			'dict',
			'req' => [
				'preferTrailingSlash' => 'bool',
				'routes' => 'list',
				'errors' => 'list',
			]
		]);
		
		$this->config =  $config;
	}
	
	/**
	 * Expects a map of inputs and invokes the matching presenter.
	 * 
	 * @param array $input
	 * A map with the following keys (as applicable depending on the running context):
	 * 
	 * - query			= $_GET:		HTTP request query fields.
	 * - body			= $_POST:		HTTP request body fields (also has $_FILES entries, see class InputFromGlobals).
	 * - cookies 		= $_COOKIE:		Cookie variables. 
	 * - server			= $_SERVER:		Server environment variables. 
	 * - env			= $_ENV:		OS environment variables.
	 * 
	 * If you just want to prepare the above array from PHP's globals, simply use InputFromGlobals::get().
	 * 
	 * The router will add the following keys when passing the above input to a controller:
	 * 
	 * - request.path	= A server neutral way to read the path (sans query string) for the current request.
	 * 
	 * The router may also add these keys when passing the above input to a controller:
	 * - tail			= "Tail" path parameters as a list of strings, if any.
	 * - vars			= "Vars" added from the route (if key "vars" is specified in the route configuration).
	 */
	public function dispatch(array $input) {
		$preferTrailingSlash = $this->config['preferTrailingSlash'];
		
		/*
		 * Index error handlers.
		 */
		
		$errorHandlers = [];
		
		foreach ($this->config['errors'] as $errorHandler) {
			$errorHandlers[$errorHandler['status']] = $errorHandler;
		}
		
		/*
		 * Try to match a route handler.
		 */
		
		$routeHandlers = $this->config['routes'];
				
		// TRICKY: Appending "?" ensures there's array index 1 for $query, even if it's empty.
		list($path, $query) = \explode('?', $input['server']['REQUEST_URI'] . '?');
				
		// To us, this is the same path: "/foo" & "/foo/", but only one of them is canonical. 
		if ($preferTrailingSlash) {
			// In this mode, for paths with a file extension (ex. "/foo.json") we use no slash, for paths without - we
			// prefer slash. An "extension" is an all lower-case ASCII letter string (no digits). We do this because we
			// want to avoid mis-detecting other uses of dots in an URL segment as an extension.
			$canonicalPath = \rtrim($path, '/') . (!preg_match('@\.[a-z]+/?$@D', $path) ? '/' : '');
			
			// The root path slash shouldn't be removed.
			if ($canonicalPath === '') $canonicalPath = '/'; 
		} else {
			$canonicalPath = \rtrim($path, '/');
			
			// The root path slash shouldn't be removed.
			if ($canonicalPath === '') $canonicalPath = '/'; 
		}
		
		// Enforce the canonical path format, if needed, and halt execution (the app will reload after the redirect).
		if ($path !== $canonicalPath) {
			\header('HTTP/1.1 301 Moved Permanently');
			\header('Location: ' . $canonicalPath . ($query !== '' ? '?' . $query : ''));
			exit;
		}
		
		$input['request'] = [];
		$input['request']['path'] = $path;
		
		
		$handler = null;
		
		foreach (\array_reverse($routeHandlers) as $route) {			
			$routePath = $route['path'];
			
			if (isset($route['tail']) && $route['tail']) {
				if (\preg_match($e = '@' . \preg_quote($routePath, '@') . '(.*)$@AD', $path, $m)) {
					// Any remaining path segments go here (as a list).
					$tail = \array_filter(\explode('/', trim($m[1], '/')));
					
					if (isset($route['tailLength'], $route['tailLengthMax'], $route['tailLengthMin'])) {
						$length = \count($tail);
						if (isset($route['tailLength']) && $length != $route['tailLength']) continue;
						if (isset($route['tailLengthMin']) && $length < $route['tailLengthMin']) continue;
						if (isset($route['tailLengthMax']) && $length > $route['tailLengthMax']) continue;
					}
					
					$input['tail'] = $tail;
					$handler = $route;
					break;
				}
			} else {
				if ($routePath === $path) {
					$handler = $route;
					break;
				}
			}
		}

		if ($handler === null) {
			if (!isset($errorHandlers[404])) throw new \Exception('There is no defined error handler for HTTP status 404.');
			$handler = $errorHandlers[404];
		}
				
		/*
		 * Invoke selected handler (route or error).
		 */
		
		$attempts = 1; 
		
		$invokeHandler = function () use (& $attempts, & $handler, & $errorHandlers, & $input, & $invokeHandler) {
			if ($attempts++ > 3) {
				throw new \Exception('Gave up after 3 attempts to execute a handler without a ControllerException (endless recursion hazard).');
			}
			
			if (isset($handler['vars'])) {
				$input['vars'] = $handler['vars'];
			} else {
				if (isset($input['vars'])) {
					unset($input['vars']);
					
					// FIXME: This alias is left for compatibility. Remove when not needed.
					unset($input['v']);
				}
			}
			
			try {
				if ($handler['call'] instanceof \Closure) {
					$handler['call']($input);
				} else {
					$class = $handler['call'];
					$controller = new $class($input);
					if (!($controller instanceof Controller)) throw new Exception('Class "' . $class  . '" should be an instance of Solver\Lab\Controller.');
				}
			} catch (ControllerException $e) {
				$code = $e->getCode();
				if (!isset($errorHandlers[$code])) throw new \Exception('There is no defined error handler for HTTP status ' . $code . '.');
				$handler = $errorHandlers[$code];
				
				$invokeHandler();
			}
		};
		
		$invokeHandler();
	}
}