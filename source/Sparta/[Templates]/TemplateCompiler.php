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
	protected static $classCache = [];
	protected $class;
	
	/**
	 * @param string $class
	 * Default = "\Solver\Sparta\Template". Target class for compilation. This affects which function calls are 
	 * detected as short method calls etc.
	 * 
	 * TODO: Compile output as actual class definitions (problem: we need to forbid inline classes, functions and 
	 * expand "use"/"namespace" context for this).
	 */
	public function __construct($class = Template::class) {
		$this->class = $class;
	}
	
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
		
		$funcToMethod = $this->getFuncToMethod();
		
		$code = '';	
		
		// This is so IDEs don't report errors in the compiled files.
		// TODO: Wrap compiled files in actual classes.
		$code .= '<?php /* @var $this ' . $this->class . ' */ ?>';
		
		foreach ($tokens as $i => $token) {
			$type = $token[0];
			$content = is_string($token) ? $token : $token[1];
			
			switch ($type) {
				// For forward compat when we compile templates as classes.
				case T_TRAIT:			
					throw new \Exception('Templates cannot contain a trait declaration, in file "' . $sourcePathname . '".');
					break;
					
				// For forward compat when we compile templates as classes.
				case T_INTERFACE:			
					throw new \Exception('Templates cannot contain an interface declaration, in file "' . $sourcePathname . '".');
					break;

				// For forward compat when we compile templates as classes.					
				case T_CLASS:	
					$prevI = $prevIndex($i);
					// We must allow Foo::class.
					if ($prevI === null || $tokens[$prevI][0] !== T_DOUBLE_COLON) {		
						throw new \Exception('Templates cannot contain a class declaration, in file "' . $sourcePathname . '".');
					}
					break;
					
				case T_CLOSE_TAG:
					// We strip new lines here so they can be added to the next T_INLINE_HTML (PHP ignores new lines
					// that follow a closing tag and we want to undo this).
					if (preg_match('/([^\n\r]+)([\n\r]+)$/AD', $content, $matches)) {
						$code .= $matches[1];
						$nextI = $nextIndex($i);
						if ($nextI === null || $tokens[$nextI][0] !== T_INLINE_HTML) {
							$code .= '<?php $this->out(\'' . $matches[2] . '\', \'none\') ?>';	
						}
					} else {
						$code .= $content;
					}
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
					$funcName = strtolower($content); // We follow the case-insensitive semantics of PHP.
					
					if (isset($funcToMethod[$funcName])) {
						$prevI = $prevIndex($i);
						$nextI = $nextIndex($i);
						
						if (
							$prevI !== null &&
							$tokens[$prevI][0] !== T_DOUBLE_COLON &&
							$tokens[$prevI][0] !== T_OBJECT_OPERATOR &&
							$tokens[$prevI][0] !== T_NS_SEPARATOR &&
							$nextI !== null && $tokens[$nextI][0] === '(') {
							$code .= '$this->' . $funcToMethod[$funcName];	
							break;
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
	
	protected function getFuncToMethod() {
		$class = $this->class;
		
		if (!isset(self::$classCache[$class])) {
			$reflClass = new \ReflectionClass($class);
			$methods = [];
			
			/* @var $reflMethod \ReflectionMethod */
			foreach ($reflClass->getMethods() as $reflMethod) {
				$methodName = $reflMethod->name;
				$functionName = strtolower(preg_replace('/[A-Z]/', '_$0', $methodName));
				
				if (!$reflMethod->isStatic() && ($reflMethod->isPublic() || $reflMethod->isProtected()) && strpos($methodName, '__') !== 0) {
					$methods[$functionName] = $methodName;	
				}
			}
			
			self::$classCache[$class] = $methods;
		}	
		
		return self::$classCache[$class];
	}
}