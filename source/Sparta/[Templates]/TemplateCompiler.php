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

use Solver\Radar\PsrxCompiler;

/**
 * Converts function calls to functions esc(), tag(), render(), import() to $this-> method calls.
 * 
 * TODO: This code is not vetted fully, edge cases may exist.
 */
class TemplateCompiler implements PsrxCompiler {
	/* (non-PHPdoc)
	 * @see \Solver\Radar\PsrxCompiler::compile()
	 */
	public function compile($sourcePathname, $symbolName) {
		$tokens = token_get_all(file_get_contents($sourcePathname));
		$tokenCount = count($tokens);
		
		$nextIndex = function ($tokenIndex) use ($tokens, $tokenCount) {
			$i = $tokenIndex;
			
			while ($i < $tokenCount - 1) {
				$i++;
				$type = $tokens[$i][0];
				if ($type !== T_COMMENT && $type !== T_DOC_COMMENT && $type !== T_WHITESPACE) return $i;
			}
			
			return null;
		};
		
		$prevIndex = function ($tokenIndex) use ($tokens, $tokenCount) {
			$i = $tokenIndex;
			
			while ($i > 0) {
				$i--;
				$type = $tokens[$i][0];
				if ($type !== T_COMMENT && $type !== T_DOC_COMMENT && $type !== T_WHITESPACE) return $i;
			}
			
			return null;
		};
		
		$code = '';	
		
		foreach ($tokens as $i => $token) {
			$type = $token[0];
			if ($type === T_STRING) {
				$name = $token[1];
				if (in_array($name, ['tag', 'esc', 'import', 'render'], true)) {
					$prevI = $prevIndex($i);
					$nextI = $nextIndex($i);
					
					if (
						$prevI !== null && $tokens[$prevI][0] !== T_DOUBLE_COLON && $tokens[$prevI][0] !== T_OBJECT_OPERATOR && 
						$nextI !== null && $tokens[$nextI][0] === '(') {
						$code .= '$this->';	
					}
				}
			}
		
			$code .= is_string($token) ? $token : $token[1];
		}
		
		return $code;
	}	
}