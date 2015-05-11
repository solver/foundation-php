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
 * Applies some useful transforms on the given PHP code:
 * 
 * - Converts function calls to functions esc(), tag(), render(), import() to $this-> method calls.
 * - Turns on auto-escape in format 'html' by default and converts inline HTML to $this->out($inline, 'raw') to preserve
 * their content.
 * - New lines after a closed PHP tag won't be ignored, but taken into account (PHP ignores them).
 * - Converts short open tags to full tags, so it doesn't matter what the PHP.ini setting is when running a template.
 * 
 * Note the output preserves the original source code lines, so error reports will give the correct line.
 */
class TemplateCompiler implements PsrxCompiler {
	/* (non-PHPdoc)
	 * @see \Solver\Radar\PsrxCompiler::compile()
	 */
	public function compile($sourcePathname, $symbolName) {
		$shortOpenTags = ini_get('short_open_tags');
		ini_set('short_open_tags', true); // We need this during tokenization to detect short tags.
		$tokens = token_get_all(file_get_contents($sourcePathname));
		ini_set('short_open_tags', $shortOpenTags);
		
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
		
		$shortMethodNames = $this->getShortMethodNames();
		$code = '';	
		// This is so IDEs don't report errors in the compiled files.
		$code .= '<?php /* @var $this \Solver\Sparta\AbstractTemplate */ ?>';
		
		foreach ($tokens as $i => $token) {
			$type = $token[0];
			$content = is_string($token) ? $token : $token[1];
			
			switch ($type) {
				case T_CLOSE_TAG:			
					// We strip new lines here so they can be added to the next T_INLINE_HTML (PHP ignores new lines
					// that follow a closing tag and we want to undo this).
					$code .= preg_replace('/([\n\r]+)/', '', $content);	
					break;
					
				case T_INLINE_HTML:
					$prevI = $prevIndex($i);
					
					if ($prevI !== null && $tokens[$prevI][0] === T_CLOSE_TAG) {
						$content = preg_replace('/([^\n\r]+)/', '', $tokens[$prevI][1]) . $content;
					}
					
					$code .= '<?php $this->out(\'' . str_replace(['\\', '\''], ['\\\\', '\\\''], $content) . '\', \'none\') ?>';
					break;
					
				case T_OPEN_TAG:
					$code .= $content === '<?' ? '<?php ' : $content;
					break;
					
				case T_STRING:
					if (in_array(strtolower($content), $shortMethodNames, true)) {
						$prevI = $prevIndex($i);
						$nextI = $nextIndex($i);
						
						if (
							$prevI !== null &&
							$tokens[$prevI][0] !== T_DOUBLE_COLON &&
							$tokens[$prevI][0] !== T_OBJECT_OPERATOR &&
							$tokens[$prevI][0] !== T_NS_SEPARATOR &&
							$nextI !== null && $tokens[$nextI][0] === '(') {
							$code .= '$this->';	
						}
					}
					
					$code .= $content;
					break;
					
				default:
					$code .= $content;
			}
		
		}
		
		return $code;
	}	
	
	/**
	 * Override this in child classes to change methods that will be callable without an explicit "$this->" in 
	 * templates.
	 * 
	 * @return array
	 */
	protected function getShortMethodNames() {
		static $methods = ['tag', 'esc', 'import', 'render', 'out'];
		return $methods;
	}
}