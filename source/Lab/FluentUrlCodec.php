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
namespace Solver\Lab;

/**
 * IMPORTANT: This class has been ported to JavaScript in solver.workspace (internal). If you update this code, update
 * the JavaScript version as well.
 * 
 * TODO: Document semantics. All of it. Like that we skip null values, empty dict and lists. Which is important.
 * TODO: Expose per-value encoders as their own thing (with the $root option), so people can use the format in other
 * contexts, like GET params.
 * FIXME: Consistent encoding of exponent in numbers (lowercase e, no plus after as it's implied and we use it for spaces).
 * TODO: Port & document changes (encoding chars + JSON-like support). The comments here are outdated.
 * TODO: Throw a more specific exception type.
 * TODO: Define behavior for Inf and NaN (best option for now: throw exception when they can encountered; disallow).
 * 
 * #FluentChain: list<name: string | dict {name: string; params: dict}>
 */
class FluentUrlCodec {
 	/**
	 * Encodes a Fluent URL.
	 * 
	 * @param array $chain
	 * #FluentChain
	 * 
	 * @return string
	 * Fluent URL (note it generates a document root based URL: "/foo/bar/" no host nor scheme).
	 * 
	 * @throw \Exception throws if the $chain is structured incorrectly.
	 */
	public static function encode($chain) {
		static $writeValue;
		
		// TODO: Add recursion limit, and implement length limit.
		// TODO: Stop early if we go past max length.
		// TODO: Optimize string building, eliminate use of implode + arrays.
		if ($writeValue === null) {
			// The root param enforced a dict value, and doesn't wrap it in parens (they're implicit). This is inserted
			// in the URL after a semicolon, like so: /foo;bar:10/.
			$writeValue = function ($input, $root = false) use (& $writeValue) {
				if ($input === null) {
					if ($root) self::throwRootMustBeCollection();
					return null;
				}
				
				elseif (is_array($input)) {
					$literals = [];
					
					for ($i = 0; isset($input[$i]); $i++) {
						$valueLiteral = $writeValue($input[$i]);
						if ($valueLiteral === null) break;
						$literals[] = $valueLiteral;
					}
					
					// TODO: Faster way?
					if (count($input) > $i) {
						foreach ($input as $key => $value) {
							// PHP automatically casts 32/64 bit integer keys to int, so we use it for cheap int detection.
							if ($key === (int) $key && $key < $i) continue;
							$keyLiteral = self::percentEncode($key);
							$valueLiteral = $writeValue($value);
							if ($valueLiteral === null) continue;
							$literals[] = $keyLiteral . '=' . $valueLiteral;
						}
					}
					
					if ($root) {
						return implode(';', $literals);
					} else {
						return $literals ? '(' . implode(';', $literals) . ')' : null;	
					}		
				}
				
				elseif (is_int($input)) {
					if ($root) self::throwRootMustBeCollection();
					return (string) $input;
				}
				
				elseif (is_string($input)) {
					if ($root) self::throwRootMustBeCollection();
					return self::percentEncode($input);
				}
				
				elseif (is_bool($input)) {
					if ($root) self::throwRootMustBeCollection();
					return $input ? '1' : '0';
				}
				
				elseif (is_float($input)) {
					if ($root) self::throwRootMustBeCollection();
					return \strtr($input, ['+' => '', 'E' => 'e']);
				}
				
				else {
					// TODO: More specific error with path etc.?
					throw new \Exception('Unsupported value type in the input.');
				}
			};
		}
		
		$url = '/';
		$count = \count($chain);

		for ($i = 0, $maxI = \count($chain); $i < $maxI; $i++) {
			$segment = $chain[$i];
			
			if (is_string($segment)) {
				$name = $segment;
				$params = null;
			} else {
				$name = $segment['name'];
				$params = $segment['params'];
			}
		 
			$url .= self::percentEncode($name);
			
			if ($params !== null) {
				$encodedParams = $writeValue($params, true);
				if ($encodedParams !== null) $url .= ';' . $encodedParams;
			}
			
			$url .= '/';
		}
		
		return $url;
	}
	
	/** 
	 * @param string $url
	 * Fluent URL (important: don't pass schema, host and query fields, only path).
	 * 
	 * @return array
	 * #FluentChain; Note that the decoder always prefers the short list item format (just a name string, no dict) if
	 * the params are empty).
	 * 
	 * @throws \Exception
	 * Throws if the URL doesn't match the correct Workspace "stack" format.
	 */
	public static function decode($url, $maxLength = 2048) {
		static $readValue, $error;
				
		if ($readValue === null) {
			$error = function ($msg, $pos, $debug, $found = null) {				
				throw new \Exception($msg . ($found !== null ? ' found "' . $found . '"' : '') . ' at path segment ' . $debug[1] . ' named "' . $debug[0] . '", parameter char ' . $pos . '.');
			};
			
			$readValue = function ($source, $length, $pos, $debug, $root = false) use (& $readValue, $error) {
				$firstChar = $source[$pos];
				
				// Collection.
				if ($firstChar === '(' || $root) {
					$i = 0;
					$positional = true;
					$collection = [];
					
					if (!$root) $pos++;
					
					nextItem:
					
					$oldPos = $pos;
					
					if ($positional) {
						list($value, $pos) = $readValue($source, $length, $pos, $debug);
						
						if ($pos == $length) {
							if ($root) {
								$collection[$i] = $value;
								return [$collection, $pos];
							} else {
								$error('Unexpected end of parameters, expecting "=", ";" or ")"', $pos, $debug);
							}
						}
						
						if ($source[$pos] === '=') {
							$key = $value;
							$positional = false;
							goto switchToNamed;
						}
						
						$collection[$i++] = $value;
					} else {
						list($key, $pos) = $readValue($source, $length, $pos, $debug);
						
						if ($pos == $length) {
							$error('Unexpected end of parameters, expecting "="', $pos, $debug);
						}
						
						if ($source[$pos] !== '=') {
							$error('Unexpected character, expecting "="', $pos, $debug);
						}						
						
						switchToNamed:
						
						$pos++;
						
						list($value, $pos) = $readValue($source, $length, $pos, $debug);						
						$collection[$key] = $value;
						
						if ($pos == $length) {
							if ($root) {
								return [$collection, $pos];
							} else {
								$error('Unexpected end of parameters, expecting ";" or ")"', $pos, $debug);
							}
						}
					}
					
					if ($pos == $length) {
						if ($root) {	
							return [$collection, $pos];
						} else {
							$error('Unexpected end of parameters, expecting ";" or ")"', $pos, $debug);
						}
					}
					
					if ($root) {
						if ($pos == $length) {
							$pos++;
							return [$collection, $pos];
						}
						
						if ($source[$pos] === ';') {
							$pos++;
							goto nextItem;
						} 
						
						$error('Unexpected character, expecting ";" or end of parameters', $pos, $debug);
					} else {
						if ($pos == $length) {
							$error('Unexpected end of parameters, expecting ";" or ")"', $pos, $debug);
						}
						
						if ($source[$pos] === ')') {
							$pos++;
							return [$collection, $pos];
						}
						
						if ($source[$pos] === ';') {
							$pos++;
							goto nextItem;
						}
						
						$error('Unexpected character, expecting ";" or end of parameters', $pos, $debug);
					}
				}
				
				// String (everything is a string in encoded fluent URLs, the rest is inferred through schemas).
				else {					
					$oldPos = $pos;
					
					// TODO: We need to disallow reserved characters that appear unencoded here.
					// TODO: Defer to a C function for this? At least regex?
					while ($pos < $length) {
						$char = $source[$pos];
						if ($char === '=' || $char === ';' || $char === ')') break;	
						$pos++;
					}
					
					return [self::percentDecode(substr($source, $oldPos, $pos - $oldPos)), $pos];
				}
			};
		}
		
		$urlSegs = \explode('/', \trim($url, '/'));
		$chain = [];
		
		foreach ($urlSegs as $i => $component) {
			$openingParenPos = \strpos($component, ';');
			
			if ($openingParenPos === false) {
				$chain[] = self::percentDecode($component);
			} else {
				$nameSrc = substr($component, 0, $openingParenPos);
				$paramsSrc = substr($component, $openingParenPos + 1);
				
				$name = self::percentDecode($nameSrc);
				$debug = [$name, $i]; // TODO: Find a way not to build this every time as it's needed for throwing exceptions only.
				list($params) = $readValue($paramsSrc, strlen($paramsSrc), 0, $debug, true);
				
				if ($params) {
					$chain[] = [
						'name' => $name,
						'params' => $params,
					];
				} else {
					$chain[] = $name;
				}
			}
		}
		
		return $chain;
	}
	
	/**
	 * Preserves unreserved characters, escapes space as +, everything else as a percent encoded sequence.
	 * 
	 * @param string $str
	 * 
	 * @return string
	 */
	protected static function percentEncode($str) {
	    return \strtr(\rawurlencode($str), ['%20' => '+']);
	}
	
	/**
	 * Opposite of percentEncode.
	 * 
	 * @param string $str
	 * 
	 * @return string
	 */
	protected static function percentDecode($str) {
	    return \rawurldecode(\strtr($str, ['+' => ' ']));
	}
	
	protected static function throwRootMustBeCollection() {
		throw new \Exception('Root value must be a collection.');
	}
}