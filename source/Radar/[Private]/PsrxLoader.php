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
namespace Solver\Radar;

class PsrxLoader implements RadarLoader {
	protected $rootDir;

	/* (non-PHPdoc)
	 * @see \Solver\Radar\RadarLoader::__construct()
	 */
	public function __construct($rootDir) {
		$this->rootDir = $rootDir;		
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Radar\RadarLoader::find()
	 */
	public function find($symbolName, $params = null) {
		/* @var $compiler PsrxCompiler */
		$compilerClass = $ruleHandlerOptions[0];
		$compilerParams = $ruleHandlerOptions[1];
		$compiler = new $compilerClass(...$compilerParams);
		file_put_contents($compiledPathname, $compiler->compile($symbolPathname, $symbolName));
	}

	/* (non-PHPdoc)
	 * @see \Solver\Radar\RadarLoader::load()
	 */
	public function load($symbolName, $params = null) {
		echo __METHOD__;
	}
}