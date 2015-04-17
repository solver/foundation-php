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
 * A barebones router.
 */
class Router {
	/**
	 * @var array
	 */
	private $config;
	
	/**
	 * @param array $config
	 * dict...
	 * - preferTrailingSlash: bool; True if you want to append slashes to URLs, unless they appear to have a file
	 * extension. False if you want to remove a trailing slash. In both cases there's one canonical version for every
	 * URL with and without a slash.
	 * - routes: list<#Route>; A list of routes (from least to most specific, last match is used).
	 * 
	 * #Route: dict...
	 * - path: string;
	 * - call: string|closure; A class name of a callable (__invoke) class, or a function to call.
	 * - tail: bool; tailLength: int; tailLengthMax
	 * - tail: boolean = false; If enabled, the router will parse path segments after the route as parameters (and puts
	 * them in $input['t']).
	 * - tailLength: null|int = null; If specified, sets a limit on the exact length the tail can be for this route to
	 * match (i.e. how many path segments). By default there's no limit.
	 * - tailLengthMax: null|int = null; If specified, sets a limit on how long the tail can be for this route to match
	 * (i.e. how many path segments). By default there's no limit.
	 * - tailLengthMin: null|int = null; If specified, sets a limit on how short the tail can be for this route to match
	 * (i.e. how many path segments). By default there's no limit.
	 * - vars: null|dict = null; If present, it'll be added to $input under key "vars". You can use this feature to pass
	 * custom data to controllers.
	 * - head?: Reserved for future use. TODO: Add this for use with multi-lingual sites ex. foo.com/en-us/page.
	 */
	public function __construct(array $config) {
		ParamValidator::validate('config', $config, [
			'dict',
			'req' => [
				'preferTrailingSlash' => 'bool',
				'routes' => 'list',
			],
		]);
		
		$this->config =  $config;
	}
	
	/**
	 * Expects a map of inputs and returns modified inputs and the matching route callback. See Dispatcher for details
	 * on the expected return format.
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
	 * 
	 * @return array
	 * dict: See Dispatcher for details on the expected return format.
	 */
	public function __invoke(array $input) {
		// As a reminder, here are some reasons why trailing slashes are preferable for user-facing URLs.
		// - Aesthetics: people prefer slashes in the end (me included - S.V.), reasons unclear.
		// - Semantics: all user-facing pages are semantically directories as noted, because either they have sub-pages
		// or can gain a sub-page at any time (which will be hosted under the page URL as if it was a directory). Files
		// can't have sub-files. And a file can't both be not a directory (no slash) and a directory (with slash).
		// - Practical: you can use relative URLs to link to child ("child/") and parent ("..") pages with trailing
		// slashes, but you can't do that without trailing slashes ("./child" works, however "." would yield the parent  
		// page with a trailing slash, causing a pointless redirect to the no-slash version).
		$preferTrailingSlash = $this->config['preferTrailingSlash'];
		
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
		if ($path !== $canonicalPath) return [301, $canonicalPath . ($query !== '' ? '?' . $query : '')];
		
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

		if ($handler === null) return [404];
		if (isset($handler['vars'])) $input['vars'] = $handler['vars'];
			
		return [200, $handler['call'], $input];
	}
}