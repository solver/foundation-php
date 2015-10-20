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
	protected $sourceRootDir, $cacheRootDir;

	/**
	 * {@inheritDoc}
	 * @see \Solver\Radar\RadarLoader::__construct()
	 */
	public function __construct($sourceRootDir, $cacheRootDir) {
		$this->sourceRootDir = $sourceRootDir;		
		$this->cacheRootDir = $cacheRootDir;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Radar\RadarLoader::find()
	 */
	public function find($symbolId, $symbolPathname, $params = null) {
		$compiledDir = $this->cacheRootDir . '/compiled';
		$compiledPathnameBase = $compiledDir . '/' .  str_replace('\\', '-', $symbolId);
		$compiledPathnameSource = $compiledPathnameBase . '.php';
		$compiledPathnameMTime = $compiledPathnameBase . '.txt';
		$sourcePathname = $this->sourceRootDir . '/' . $symbolPathname;
		
		if (!file_exists($sourcePathname)) return null;
		$sourceMTime = (string) filemtime($sourcePathname);
		
		if (!file_exists($compiledPathnameMTime) || file_get_contents($compiledPathnameMTime) !== $sourceMTime) {
			if (!is_dir($compiledDir)) mkdir($compiledDir, 0777, true);
			
			/* @var $compiler PsrxCompiler */
			list($compilerClass, $compilerParams) = $params;
			$compiler = new $compilerClass(...$compilerParams);
			
			file_put_contents($compiledPathnameSource, $compiler->compile($sourcePathname, $symbolId));
			file_put_contents($compiledPathnameMTime, $sourceMTime);
		}
		
		return $compiledPathnameSource;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Radar\RadarLoader::load()
	 */
	public function load($symbolId, $symbolPathname, $params = null) {
		$path = $this->find($symbolId, $symbolPathname, $params);
		if ($path == null) {
			return [false, null];
		} else {
			return [true, require $path];
		}
	}
}