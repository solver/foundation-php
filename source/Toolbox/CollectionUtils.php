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
	 * Takes a one-dimensional array where the keys may contain "." and "[]" (or a set of other configurable delimiters)
	 * to define array paths, and converts them to actual array paths, i.e.:
	 * 
	 * <code>
	 * $arr['foo.bar'] becomes $arr['foo']['bar']
	 * </code>
	 * 
	 * @param array $array
	 * Array to be integrated.
	 * 
	 * @param string $delim
	 * Optional (default '.[]'). One or more characters to split the paths by (every character individually will act as
	 * a delimiter, not as a sequence).
	 * 
	 * @return array
	 * A new array with the integrated subarrays.
	 */
	static public function splitKeys(array $array, $delim = '.[]') {
		$out = [];
		
		// TODO: Optimization opportunity?
		foreach ($array as $key => $item) {
			$parent = & self::drill($out, $key, $topKey, true, true, $delim);
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
						$map($arrayRef, $key == '' ? $subKey : $key.$delim.$subKey, $subVal, $delim);
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
	 * deep array item by passing the array and path to the item as a delimited string.
	 * 
	 * Drills the array to the last but second path segment and returns the parent (by reference), and the last segment
	 * key for further operations. With these references you are free to read/set/unset the element in the original
	 * array without further loops and complicated logic.
	 *  
	 * Example usage:
	 * 
	 * <code>
	 * // In this example we operate on $arrayRef['a']['b']['c'].
	 * $path = 'a.b.c';
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
	 * @param string $path
	 * Array path identifier, for example: 'abc[def][ghi]', or 'abc.def.ghi'.
	 * 
	 * @param null|string $keyOut
	 * Returns in this var the key under which the element is found (as per path spec). Null if there's no valid parent
	 * array (and it couldn't be created depending on the bool flags).
	 * 
	 * @param bool $createMissingAncestors
	 * Optional (default = false). When true, if the ancestor arrays for the given path don't exist, they'll be created
	 * as long as they're not already set to a conflicting type (a scalar, resource, object). When enabled, this
	 * behavior matches a trait of PHP called "array promotion".
	 * 
	 * @param bool $replaceInvalidAncestors
	 * Optional (default = false). When true, if any ancestors for the given path are set to a conflicting type  (a 
	 * scalar, object, resource) they'll be silently replaced by empty arrays in order to create the path as requested.
	 * 
	 * @param string $delim
	 * One or more chars that will be considered delimiters between path segments, by default ".". You can add "[]" to
	 * this string, and the function will also parse the default PHP array path convention (for ex. "foo[bar][baz]").
	 * 
	 * @return null|array
	 * The parent array of the element, by reference. Null if there's no valid parent array (and it couldn't be created
	 * depending on the bool flags).
	 */
	public static function & drill(array & $arrayRef, $path, & $keyOut, $createMissingAncestors = false, $replaceInvalidAncestors = false, $delim = '.') {
		$parent = & $arrayRef;
		$keyOut = \strtok($path, $delim);
		
		start:
			$nextKey = \strtok($delim);
			if ($nextKey === false) return $parent; 
			
			if (isset($parent[$keyOut])) {
				if (!\is_array($parent[$keyOut])) {
					if ($replaceInvalidAncestors) {
						$parent[$keyOut] = [];
					} else {
						goto fail;
					}
				}
			} else {
				if ($createMissingAncestors) {
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