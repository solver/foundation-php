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
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Radar\PsrxCompiler::compile()
	 */
	public function compile($sourcePathname, $symbolName) {
		return $this->compileFromString(file_get_contents($sourcePathname), $sourcePathname, $symbolName);
	}
	
	public function compileFromString($source, $sourcePathname, $symbolName) {
		if (!$this->hasShortTags()) throw new \Exception('Short open tags must be enabled during compilation.');
		
		$tokens = token_get_all($source);
		$tokenCount = count($tokens);
		
		// Finds next significant token index.
		$nextIndex = function ($i) use ($tokens, $tokenCount) {
			while ($i < $tokenCount - 1) {
				$tokenType = $tokens[++$i][0];
				if ($tokenType !== T_COMMENT && $tokenType !== T_DOC_COMMENT && $tokenType !== T_WHITESPACE) return $i;
			}
			
			return null;
		};
		
		// Finds previous significant token index.
		$prevIndex = function ($i) use ($tokens) {
			while ($i > 0) {
				$tokenType = $tokens[--$i][0];
				if ($tokenType !== T_COMMENT && $tokenType !== T_DOC_COMMENT && $tokenType !== T_WHITESPACE) return $i;
			}
			
			return null;
		};
		
		// TODO: Verify here $this->class is instanceof \Solver\Sparta\Template (we accept only this and subclasses of it).
		$funcToMethod = $this->getFuncToMethod();
		
		// The @var is so IDEs don't report errors in the compiled files. TODO: Wrap compiled files in actual classes?
		$code = '<?php /* @var $this ' . $this->class . ' */ ?>';
		$prependToInlineHtml = false;
		
		foreach ($tokens as $i => $token) {
			if (isset($token[1])) {
				$tokenType = $token[0];
				$tokenContent = $token[1];
			} else {				
				$tokenType = $tokenContent = $token;
			}
			
			switch ($tokenType) {
				// For forward compat when we compile templates as classes.
				case T_TRAIT:			
				case T_INTERFACE:					
				case T_CLASS:	
					// Edge case, we must allow syntax like "Foo::class".
					if ($tokenType === T_CLASS) {
						$prevI = $prevIndex($i);
						if ($prevI !== null && $tokens[$prevI][0] === T_DOUBLE_COLON) {		
							$code .= $tokenContent;
							break;
						}
					}
					throw new \Exception('Templates cannot contain a ' . $tokenContent . ' declaration' . $this->getContext($sourcePathname, $tokens, $i));
					break;
					
				// We want to prevent people from using $this. The underlying implementation of the pseudo-functions
				// might (i.e. will) change and they might not be on $this->*() anymore, but for ex. $this->api->*().
				// Additionally, not all protected methods are part of the official template API.
				case T_VARIABLE:
					if ($tokenContent === '$this') {
						throw new \Exception('Templates cannot contain references to $this, please use the provided API functions instead' . $this->getContext($sourcePathname, $tokens, $i));
					}
					$code .= $tokenContent;
					break;
										
				case T_CLOSE_TAG:
					// We detect new lines here so they can be added to the next T_INLINE_HTML (PHP ignores new lines
					// that follow a closing tag and we want them to be respected).
					$closeTagNewLines = substr($tokenContent, 2);
					
					if ($closeTagNewLines) {
						$code .= '?>';
						$nextI = $nextIndex($i);
						
						if ($nextI !== null && $tokens[$nextI][0] == T_INLINE_HTML) {
							$prependToInlineHtml = $closeTagNewLines; // Used by the following T_INLINE_HTML.
						} else {
							$code .= '<?php $this->echoRaw(\'' . $closeTagNewLines . '\') ?>';	
						}
					} else {
						$code .= $tokenContent;
					}
					break;
					
				case T_INLINE_HTML:
					$prevI = $prevIndex($i);
					
					if ($prependToInlineHtml) {
						$tokenContent = $prependToInlineHtml . $tokenContent;
						$prependToInlineHtml = false;
					}
					
					// TODO: Test if this str_replace has edge cases for combinations of consecutive \ and ' chars. 
					$code .= '<?php $this->echoRaw(\'' . str_replace(['\\', '\''], ['\\\\', '\\\''], $tokenContent) . '\') ?>';
					break;
					
				case T_OPEN_TAG:
					if ($tokenContent === '<?') {
						$nextI = $i + 1;
						
						// We want to avoid situations like <?xml...
						if (isset($tokens[$nextI]) && $tokens[$nextI][0] === T_STRING) {
							throw new \Exception('Ambiguous short tag syntax "<?' . $tokens[$nextI][1] . '...", use a space or a new line after the short tag to disambiguate: "<? ' . $tokens[$nextI][1] . '..."' . $this->getContext($sourcePathname, $tokens, $i));
						}
						
						$code .= '<?php ';
					} else {
						$code .= $tokenContent;
					}
					break;
					
				case T_STRING:
					$funcName = \strtolower($tokenContent); // We follow the case-insensitive semantics of PHP.
					
					if (isset($funcToMethod[$funcName])) {
						$prevI = $prevIndex($i);
						$nextI = $nextIndex($i);
						
						if ($prevI !== null && $nextI !== null) {
							$prevType = $tokens[$prevI][0];
							$nextType = $tokens[$nextI][0];
							
							if ($prevType !== T_DOUBLE_COLON &&
								$prevType !== T_OBJECT_OPERATOR &&
								$prevType !== T_NS_SEPARATOR &&
								$nextType === '(') {
								$code .= '$this->' . $funcToMethod[$funcName];
								break;
							}
						}
					}
					
					$code .= $tokenContent;
					break;
					
				default:
					$code .= $tokenContent;
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
		
		// TODO: Use the token's "line" field (index 2) instead.
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
	
	private function tagNameAnalysis($name) {
		// TODO: This method is not used right now, it analyzes tag($name) strings, possible future optimization.
		
		preg_match('#(/)?(@)?(\w+)(@(\w+))?(/)?$#AD', $name, $matches);
		$open = $matches[1] === '';
		$param = $matches[2] !== '';
		$name = $matches[3];
		$shortcutParam = isset($matches[5]) ? $matches[5] : null;	
		$selfClose = isset($matches[6]);
		
		return [$name, $open, $param, $shortcutParam, $selfClose];
	}
}