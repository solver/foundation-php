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
namespace Solver\Sparta;

interface Router {
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
	 * - router			= Variables added from the router, the specific vars depend on the router and its config.
	 * 
	 * @return array
	 * dict: See Dispatcher for details on the expected return format.
	 */
	public function __invoke(array $input);
}