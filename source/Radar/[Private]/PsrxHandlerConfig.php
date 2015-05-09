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

/**
 * Returned by methods of PsrxConfig to complete the handler configuration.
 */
class PsrxHandlerConfig {
	protected $type, $name, $callback;
	
	public function __construct($type, $name, \Closure $callback) {
		$this->type = $type;
		$this->name = $name;
		$this->callback = $callback;
	}
	
	/**
	 * Causes a file or directory to be ignored (no errors, no scan).
	 */
	public function asIgnored() {
		$this->callback->__invoke($this->type, $this->name, 'ignore', null);		
	}
	
	/**
	 * Declares a file or directory as a standard PHP file. This the default handler for extension ".php". 
	 */
	public function asPhp() {	
		if ($this->type !== 'file' && $this->type !== 'fileExt') {
			throw new \Exception('Handler "php" only applies to files.');
		}
		$this->callback->__invoke($this->type, $this->name, 'php', null);	
	}
	
	/**
	 * Declares a directory (where __config.php is) as a file resource directory.
	 * 
	 * - Files in this dir and subdirectories will be treated as static resources, and won't be scanned.
	 * - This includes file __config.php in a resource directory: it'll be ignored, unless you're declaring a directory
	 * as a resource directory from the __config.php file within that directory (which is supported).
	 * - The location of this directory will be exposed as a symbol in Radar's API (so you can find this directory's 
	 * location by its symbol name). 
	 * - If you compile a simplified source tree all the files and subdirectories will be copied, preserving their 
	 * original layout relative to the resource directory.
	 */
	public function asResourceDir() {	
		if ($this->type !== 'dir') {
			throw new \Exception('Handler "resourceDir" only applies to directories.');
		}
		
		$this->callback->__invoke($this->type, $this->name, 'res', null);
	}
	
	/**
	 * Declares a file as a symbol (class/function) compiled to PHP by the given compiler).
	 * 
	 * @param string $compilerClass
	 * Name of a class implementing interface PsrxCompiler. It'll be created on demand to handle files as necessary.
	 * 
	 * @param array ...$compilerArgs
	 * Optional. Parameters to pass to the compiler's constructor.
	 * 
	 * @throws \Exception
	 */
	public function asCompiledBy($compilerClass, ...$compilerArgs) {	
		if ($this->type !== 'file' && $this->type !== 'fileExt') {
			throw new \Exception('Handler "compiledBy" only applies to files.');
		}
		$this->callback->__invoke($this->type, $this->name, 'compiled', [$compilerClass, $compilerArgs]);	
	}
}