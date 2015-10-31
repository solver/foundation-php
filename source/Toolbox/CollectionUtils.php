<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
namespace Solver\Toolbox;

/**
 * Utilities for working with PHP arrays representing an arbitrary collection type (this class has no specific 
 * expectations about the format of the given array structure, keys, values).
 * 
 * The operations here are relevant to dicts, lists, tuples, sets, tables, etc.
 */
class CollectionUtils {
	/**
	 * Takes a one-dimensional array where the keys may contain "." (or another configurable delimiter)
	 * to define array paths, and converts them to actual array paths, i.e.:
	 * 
	 * <code>
	 * $arr['foo.bar'] becomes $arr['foo']['bar']
	 * </code>
	 * 
	 * @param array $array
	 * Array whose keys to split.
	 * 
	 * @param string $delim
	 * Optional (default '.'). A string to split the paths by.
	 * 
	 * @return array
	 * A new array with split keys.
	 */
	static public function splitKeys(array $array, $delim = '.') {
		$out = [];
		$topKey = null;
		
		// TODO: Optimization opportunity?
		foreach ($array as $key => $item) {
			$parent = & self::drill($out, explode($delim, $key), $topKey, true, true);
			$parent[$topKey] = $item;
		}
		
		return $out;
	}
	
	/**
	 * Takes a multi-dimensional hashmap and converts it to a flat hashmap of each item with full array path, i.e.:
	 * 
	 * <code>
	 * $arr['foo']['bar'] becomes $arr['foo.bar']
	 * </code>
	 * 
	 * This function should not be used with arrays containing circular references.
	 * 
	 * @param array $array
	 * Array to be integrated.
	 * 
	 * @param string $delim
	 * Optional (default '.'). Custom delimiter character to use for the resulting paths.
	 * 
	 * @return array
	 * A new array with the integrated subarrays.
	 */
	static public function joinKeys(array $array, $delim = '.') {
		static $map;
		
		if ($map === null) {
			$map = static function (& $arrayRef, $key, $val, $delim) use (& $map) {
				if (is_array($val) && $val) {
					foreach ($val as $subKey => $subVal) {
						$map($arrayRef, $key == '' ? $subKey : $key . $delim . $subKey, $subVal, $delim);
					}
				} else {
					$arrayRef[$key] = $val;
				}
			};
		}
		
		$out = [];		
		$map($out, '', $array, $delim);
				
		return $out;
	}
	
	/**
	 * This is a low-level operation, that can be used to implement set, get, isset, unset, push etc. operations on a
	 * deep array item by passing the array and path to the item as a list of strings.
	 * 
	 * Drills the array to the last but second path segment and returns the parent (by reference), and the last segment
	 * key for further operations. With these references you are free to read/set/unset the element in the original
	 * array without further loops and complicated logic.
	 *  
	 * Example usage:
	 * 
	 * <code>
	 * // In this example we operate on $arrayRef['a']['b']['c'].
	 * $path = ['a', 'b', 'c'];
	 * 
	 * // Don't forget to take the result by reference if you want to modify.
	 * $parent = & CollectionUtils::drill($arrayRef, $path, $keyOut); 
	 * 
	 * // Perform this check to confirm we can set keys on this parent (if it's not null, it's a valid array).
	 * $canset = $parent !== null;
	 * 
	 * // Same as above (either will $parent and $keyOut be both null, or neither).
	 * $canset = $keyOut !== null;
	 * 
	 * // Isset example (treats a set null value as "not set"; if indesirable, see next example).
	 * $isset = isset($parent[$keyOut]);
	 * 
	 * // Key exists example (notice we must check parent is an array first to avoid PHP errors).
	 * $keyexists = $parent !== null && key_exists($keyOut, $parent);
	 * 
	 * // Same as above (either will $parent and $keyOut be both null, or neither).
	 * $keyexists = $keyOut !== null && key_exists($keyOut, $parent);
	 * 
	 * // Get example (before: you MUST first perform the $isset or $keyexists check above to avoid errors).
	 * echo $parent[$keyOut]; 
	 * 
	 * // Set example (before: you MUST first perform the $canset check above to avoid errors).
	 * $parent[$keyOut] = 123; 
	 * 
	 * // Unset example (before: you MAY first perform the $isset or $keyexists check above; won't error either way).
	 * unset($parent[$keyOut]);
	 * 
	 * // Single expression get (if you're sure the item exists):
	 * echo CollectionUtils::drill($arrayRef, $path, $keyOut)[$keyOut];
	 * 
	 * // Single expression set (if you're sure the item can be set):
	 * CollectionUtils::drill($arrayRef, $path, $keyOut)[$keyOut] = 123;
	 * 
	 * // Single expression unset (always works):
	 * unset(CollectionUtils::drill($arrayRef, $path, $keyOut)[$keyOut]);
	 * </code>
	 * 
	 * @param array $arrayRef
	 * Array reference to be scanned.
	 * 
	 * @param array $path
	 * Array path identifier, for example to acces $array['abc']['def']['ghi'], provide path ['abc', 'def', 'ghi'].
	 * 
	 * @param null|string $keyOut
	 * Returns in this var the key under which the element is found (as per path spec). Null if there's no valid parent
	 * array (and it couldn't be created depending on the bool flags).
	 * 
	 * @param bool $promote
	 * Optional (default = false). When true, if the ancestor arrays for the given path don't exist (or are null),
	 * they'll be created as long as they're not already set to a conflicting type (a scalar, resource, object). When
	 * enabled, this behavior matches a trait of PHP called "array promotion".
	 * 
	 * @param bool $replace
	 * Optional (default = false). When true, if any ancestors for the given path are set to a conflicting type  (a 
	 * scalar, object, resource) they'll be silently replaced by empty arrays in order to create the path as requested.
	 * 
	 * @return null|array
	 * The parent array of the element, by reference. Null if there's no valid parent array (and it couldn't be created
	 * depending on the bool flags).
	 */
	public static function & drill(array & $arrayRef, array $path, & $keyOut, $promote = false, $replace = false) {
		$count = count($path);
			
		if ($promote) goto generic;
		
		// TODO: Possible optimization, directly assigning non-existing path by ref to use PHP's native promoting
		// behavior (no error is fired). One issue is incompatible ancestors, object/resource/string/number will throw
		// an error, then a warning. If we can handle those edge cases, it'll be a much faster way to promote.
		switch ($count) {
			 case 1: 
				$keyOut = $path[0];
				return $arrayRef;
			 case 2: 
				list($a, $keyOut) = $path;
				if (isset($arrayRef[$a])) $arrayRef = & $arrayRef[$a]; else goto fail;
				goto isArrayOrFail;
			 case 3: 
				list($a, $b, $keyOut) = $path;
				if (isset($arrayRef[$a][$b])) $arrayRef = & $arrayRef[$a][$b]; else goto fail;
				goto isArrayOrFail;
			 case 4: 
				list($a, $b, $c, $keyOut) = $path;
				if (isset($arrayRef[$a][$b][$c])) $arrayRef = & $arrayRef[$a][$b][$c]; else goto fail;
				goto isArrayOrFail;
			 case 5: 
				list($a, $b, $c, $d, $keyOut) = $path;
				if (isset($arrayRef[$a][$b][$c][$d])) $arrayRef = & $arrayRef[$a][$b][$c][$d]; else goto fail;
				goto isArrayOrFail;
			 case 6:
				list($a, $b, $c, $d, $e, $keyOut) = $path;
				if (isset($arrayRef[$a][$b][$c][$d][$e])) $arrayRef = & $arrayRef[$a][$b][$c][$d][$e]; else goto fail;
				goto isArrayOrFail;
			 case 7:
				list($a, $b, $c, $d, $e, $f, $keyOut) = $path;
				if (isset($arrayRef[$a][$b][$c][$d][$e][$f])) $arrayRef = & $arrayRef[$a][$b][$c][$d][$e][$f]; else goto fail;
				goto isArrayOrFail;
			 case 8:
				list($a, $b, $c, $d, $e, $f, $g, $keyOut) = $path;
				if (isset($arrayRef[$a][$b][$c][$d][$e][$f][$g])) $arrayRef = & $arrayRef[$a][$b][$c][$d][$e][$f][$g]; else goto fail;
				goto isArrayOrFail;
			default:
				goto generic;
		}
		
		isArrayOrFail:
		if (is_array($arrayRef)) return $arrayRef; 
		if ($replace) { $arrayRef = []; return $arrayRef; }
		goto fail;
		
		generic:
		for ($i = 0, $lastI = $count - 1; $i < $lastI; $i++) {
			$seg = $path[$i];	
			
			if (!isset($arrayRef[$seg])) {
				if ($promote) $arrayRef[$seg] = [];
				else goto fail;
			}
			
			$arrayRef = & $arrayRef[$seg];
				
			if (!is_array($arrayRef)) {
				if ($replace) $arrayRef = []; 
				else goto fail;
			}
		}		
			
		$keyOut = $path[$lastI];
		return $arrayRef;
		
		fail:
		$keyOut = null;
		$nothing = null; 
		return $nothing;
	}
	
	// Left here for reference. Remove once we're sure we don't need this. Old method which works with delimited string paths and is slower in most cases.
	// One edge case where this function is a bit faster is promoting/replacing a deep path from a nearly empty array (ntoe fast code path is not used with the
	// new method when we have to $promote) on a path that started as a string. I.e. explode('.', $path) + new drill + promote is slower than this drill + promote. 
	// Research.
	private static function & drill_OLD(array & $arrayRef, $path, & $keyOut, $promote = false, $replace = false, $delim = '.') {
		$parent = & $arrayRef;
		$keyOut = \strtok($path, $delim);
		
		start:
			$nextKey = \strtok($delim);
			if ($nextKey === false) return $parent; 
			
			if (isset($parent[$keyOut])) {
				if (!\is_array($parent[$keyOut])) {
					if ($replace) {
						$parent[$keyOut] = [];
					} else {
						goto fail;
					}
				}
			} else {
				if ($promote) {
					$parent[$keyOut] = []; 
				} else {
					goto fail;
				}
			}
			
			$parent = & $parent[$keyOut]; 
			$keyOut = $nextKey;
		goto start;
		
		fail:		
			// TRICKY: We need to unset before we set to null, or we'll alter the array given to us by ref.
			unset($parent);
			
			$keyOut = null;
			$parent = null; 
			
			return $parent;
	}
	
	/**
	 * Converts the dot path syntax (ex. 'foo.bar.baz') to standard PHP bracket array path (ex. 'foo[bar][baz]'). Mixed
	 * dot and brackets syntax isn't supported.
	 * 
	 * To specify "append to array" ('foo[][bar][baz][]') in dot syntax, use repeat/trailing dot ('foo..bar.baz.').
	 * 
	 * @param string $path
	 * 
	 * @param string $delim
	 * Optional (default '.'). Custom delimiter character to read in the input.
	 * 
	 * @return string
	 */
	static public function dotToBracket($path, $delim = '.') { 	
		// not checking explicit false as position 0 is not correct syntax in this case
		if ($pos = \strpos($path, $delim)) {
			$path = \str_replace($delim, '][', $path);
			return \substr_replace($path, '', $pos, 1).']'; 			
		} else { // no dots (no processing)
			return $path;
		}
		
	}	
	
	/**
	 * Converts standard bracket array PHP path ('foo[bar][baz]') to dot array syntax (ex. 'foo.bar.baz'). Mixed dot and
	 * brackets syntax isn't supported.
	 * 
	 * @param string $path
	 * 
	 * @param string $delim
	 * Optional (default '.'). Custom delimiter character to write in the output.
	 * 
	 * @return string
	 */
	static public function bracketToDot($path, $delim = '.') {		
		// not checking explicit false as position 0 is not correct syntax in this case
		if ($pos = \strpos($path, '[')) {
			$path = \substr_replace($path, ']', $pos, 0);
			return \str_replace('][', $delim, \substr($path, null, -1)); 			
		} else { // no brackets (no processing)
			return $path;
		}
	}
}