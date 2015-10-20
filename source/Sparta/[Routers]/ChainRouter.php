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

/**
 * Runs a list of routers by running them one by one until a match is found (non-404 response). That match is returned.
 */
class ChainRouter implements Router {
	/**
	 * @var Router[]
	 */
	protected $routers;
	
	/**
	 * @param Router[] ...$routers
	 * ($routeCollection: FastRoute\RouteCollector) => void;
	 */
	public function __construct(Router ...$routers) {
		$this->routers = $routers;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sparta\Router::__invoke()
	 */
	public function __invoke(array $input) {
		$routers = $this->routers;
		
		foreach ($routers as $router) {
			$resolution = $router($input);
			if ($resolution[0] !== 404) return $resolution;
		}
		
		return [404];
	}
}