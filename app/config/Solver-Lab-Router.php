<?php
/*
 * Router configuration.
 * 
 * List routes from least to most specific (the last match gets dispatched). For details, see class Solver\Lab\Router.
 */
return [
	'preferTrailingSlash' => true,
		
	// Begin each route path with a "/". Use the correct URL style for your path, depending on your preferTrailingSlash
	// setting.
	'routes' => [
		['path' => '/example/',					'call' => 'Blitz\ExampleController', 'tail' => true /* Optional */, 'tailLength' => 1 /* Optional */],
	],
	
	// Define error controllers for at least these:
	// - 404 (Not Found).
	// - 500 (Internal Server Error).
	'errors' => [
		['status' => 403,						'call' => 'Example\ErrorController', 'vars' => ['status' => 403]],
		['status' => 404,						'call' => 'Example\ErrorController', 'vars' => ['status' => 404]],
		['status' => 500,						'call' => 'Example\ErrorController', 'vars' => ['status' => 500]],
	]
];