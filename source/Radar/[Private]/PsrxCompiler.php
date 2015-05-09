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
 * Interface compilers should implement.
 */
interface PsrxCompiler {
	/**
	 * Takes path to a source file and symbol to compile (typically a class) and returns source text to the compiled
	 * symbol.
	 * 
	 * @param string $sourcePathname
	 * Full pathname to the source file that should be compiled.
	 * 
	 * @param string $symbolName
	 * Symbol name (i.e. class name) the compiled source should define.
	 * 
	 * @return string $source
	 * Compiled source code to load for this symbol.
	 */
	public function compile($sourcePathname, $symbolName);
}