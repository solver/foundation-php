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

/*
 * Application boot-up.
 * 
 * This file sets up the app, it's the only file Apache invokes directly for the entire site.
 */
 
use Solver\Sparta\Router;
use Solver\Base\InputFromGlobals;
use Solver\Lab\Dispatcher;

/*
 * Define global constants.
 */

// You can use this instead of $_SERVER['DOCUMENT_ROOT'].
define('DOC_ROOT', rtrim(dirname(__DIR__), '\\/'));

// All code looks here for classes, config, cache and so on.
define('APP_ROOT', DOC_ROOT . '/app');

// Allows developer pages, displays errors & uncaught exceptions etc. DO NOT ENABLE ON PRODUCTION.
define('DEBUG', true); 

// Temporary.
function debuglog($message) {
	if (DEBUG) {
		file_put_contents(APP_ROOT . '/../../log.txt', $message . "\n", FILE_APPEND);
	}
}

/*
 * Initialize core framework services.
 */

require __DIR__ . '/core.php';

Solver\Lab\Core::init([
	'classes' => '',
	'templates' => '',
	
	// Composer has a class map for the classes, but it ignores the templates, and it doesn't automatically update the
	// classmap as we apply patches to Foundation (and commit them back to the package), so we'll need Foundation to
	// scan itself (and Composer's map is used only to bootstrap it).
	'vendor/solver/foundation/app/classes' => '',
]);

/*
 * Run app.
 */

// Output has to be buffered so we can send proper headers to close the connection early (see below).
ob_start();

// Routes are listed from least to most specific (the last match gets dispatched). Begin each route path with a "/". 
// Unless you have a file ext. (".json", ".xml" etc.), end with a "/" (or not) if using a preferTrailingSlash mode (or 
// not). For more info: see Solver\Sparta\Router.
$routes = [
	['path' => '/example/', 'call' => 'Example\ExamplePage', 'tail' => true /* Optional */, 'tailLength' => 1 /* Optional */],
];

$router = new Router([
	'preferTrailingSlash' => true,
	'routes' => $routes,
]);

$dispatcher = new Dispatcher($router, 'Example\ErrorPage');
$dispatcher->dispatch(InputFromGlobals::get());

if (!\DEBUG) { // We don't run the following headers in debug mode so we can see all errors.
	header('Content-Length: ' . ob_get_length());
	header('Connection: close');
}

// These ensure the connection will truly close now, and we can perform slow action (see below) without the browser waiting.
ob_end_flush();
flush();
if (session_id()) session_write_close();

/*
 * Post-page slow actions.
 */

// Nothing here yet.