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
 * - Converts short open tags to full tags, so it doesn't matter what the PHP.ini setting is when running a template. It
 * also promotes safe use of short tags by forbidding ambiguous syntax like <?xml ?>, instead you must have a space
 * after opening a short tag: <? xml ?> or escape it if you want to output an XML directive: <?= "<?xml ?>" ?>
 * 
 * Note the output preserves the original source code lines, so error reports will give the correct line.
 */
class TemplateCompiler implements PsrxCompiler {
	protected static $hasShortTags = null;
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
		if (!$this->hasShortTags()) throw new \Exception('Short open tags must be enabled during compilation.');
		
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
		
		// TODO: Verify here $this->class is instanceof \Solver\Sparta\Template (we accept only this and subclasses of it).
		$funcToMethod = $this->getFuncToMethod();
		
		$code = '';
		
		// This is so IDEs don't report errors in the compiled files.
		// TODO: Wrap compiled files in actual classes.
		$code .= '<?php /* @var $this ' . $this->class . ' */ ?>';
		
		// TODO: Add ability to count and report the line of a token after the fact on error.
		foreach ($tokens as $i => $token) {
			$type = $token[0];
			$content = is_string($token) ? $token : $token[1];
			
			switch ($type) {
				// For forward compat when we compile templates as classes.
				case T_TRAIT:			
					throw new \Exception('Templates cannot contain a trait declaration' . $this->getContext($sourcePathname, $tokens, $i));
					break;
					
				// For forward compat when we compile templates as classes.
				case T_INTERFACE:			
					throw new \Exception('Templates cannot contain an interface declaration' . $this->getContext($sourcePathname, $tokens, $i));
					break;

				// For forward compat when we compile templates as classes.					
				case T_CLASS:	
					$prevI = $prevIndex($i);
					// We must allow Foo::class.
					if ($prevI === null || $tokens[$prevI][0] !== T_DOUBLE_COLON) {		
						throw new \Exception('Templates cannot contain a class declaration' . $this->getContext($sourcePathname, $tokens, $i));
					} else {
						$code .= $content;
					}
					break;
					
				case T_CLOSE_TAG:
					// We strip new lines here so they can be added to the next T_INLINE_HTML (PHP ignores new lines
					// that follow a closing tag and we want to undo this).
					if (preg_match('/([^\n\r]+)([\n\r]+)$/AD', $content, $matches)) {
						$code .= $matches[1];
						$nextI = $nextIndex($i);
						if ($nextI === null || $tokens[$nextI][0] !== T_INLINE_HTML) {
							$code .= '<?php $this->echoRaw(\'' . $matches[2] . '\') ?>';	
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
					
					$code .= '<?php $this->echoRaw(\'' . str_replace(['\\', '\''], ['\\\\', '\\\''], $content) . '\') ?>';
					break;
					
				case T_OPEN_TAG:
					if ($content === '<?') {
						$nextI = $i + 1;
						
						// We want to avoid situations like <?xml...
						if (isset($tokens[$nextI]) && $tokens[$nextI][0] === T_STRING) {
							throw new \Exception('Ambiguous short tag syntax "<?' . $tokens[$nextI][1] . '...", use a space or a new line after the short tag to disambiguate: "<? ' . $tokens[$nextI][1] . '..."' . $this->getContext($sourcePathname, $tokens, $i));
						}
						
						$code .= '<?php ';
					} else {
						$code .= $content;
					}
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
	
	// TODO: Move this method to the target class instead to it can control which methods get included and which don't.
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
	
	protected function getContext($sourcePathname, $tokens, $i) {
		return ', in file "' . $sourcePathname . '", line ' . $this->getLineOf($tokens, $i) . '.';
	}
	
	protected function getLineOf($tokens, $i) {
		$code = '';
		
		foreach ($tokens as $j => $token) {
			if ($j == $i) break;
			$code .= is_array($token) ? $token[1] : '';
		}
		
		return strlen(preg_replace('/[^\n]/', '', $code)) + 1;
	}
	
	protected function hasShortTags() {
		if (self::$hasShortTags === null) {
			// Using ini_get('short_open_tags') is not reliable for some reason.
			self::$hasShortTags = (bool) eval('ob_start(); ?><? ob_end_clean(); return true ?><?php ob_end_clean();'); 
		}
		
		return self::$hasShortTags;
	}
}