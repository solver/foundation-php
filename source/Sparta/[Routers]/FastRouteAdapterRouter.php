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
 * Simple adapter for nikic/fast-route.
 * 
 * This adapter doesn't require FastRoute's functions.php to be loaded in order to function.
 */
class FastRouteAdapterRouter implements Router {
	/**
	 * @var \FastRoute\Dispatcher\GroupCountBased
	 */
	protected $dispatcher;
	
	/**
	 * @param \Closure $routeDefinitionCallback
	 * ($routeCollection: FastRoute\RouteCollector) => void;
	 */
	public function __construct(\Closure $routeDefinitionCallback) {
		$options = [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'FastRoute\\RouteCollector',
        ];

        /* @var $routeCollector \FastRoute\RouteCollector */
        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        
        $routeDefinitionCallback($routeCollector);
		
        $this->dispatcher = new $options['dispatcher']($routeCollector->getData());
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sparta\Router::__invoke()
	 */
	public function __invoke(array $input) {
		$routeInfo = $this->dispatcher->dispatch(
			$input['server']['REQUEST_METHOD'],
			$input['server']['REQUEST_URI']
		);
		
		switch ($routeInfo[0]) {
		    case \FastRoute\Dispatcher::NOT_FOUND:
		    case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
		        return [404];
		        
		    case \FastRoute\Dispatcher::FOUND:
		        $handler = $routeInfo[1];
		        $input['vars'] = $routeInfo[2];
		        return [200, $handler, $input];
		}
	}
}