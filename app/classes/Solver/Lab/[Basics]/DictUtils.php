<?php
namespace Solver\Lab;

/**
 * Utilities for working with & processing PHP arrays representing a dictionary (i.e. associative arrays).
 * 
 * @author Stan Vass
 * @copyright © 2012-2013 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class DictUtils {
	/**
	 * Returns a filtered dictionary where any keys not present in the whitelist of keys are removed.
	 * 
	 * Also see FUNCTIONNAME for a more generic approach than whitelisting and blacklisting.
	 * 
	 * @param array $dict
	 * Dictionary to filter.
	 * 
	 * @param array $keys
	 * List of key names to allow.
	 */
	public static function whitelist(array $dict, array $keys) {
		$out = [];
		
		foreach ($keys as $key) if (\key_exists($key, $dict)) {
			$out[$key] = $dict[$key];
		}
		
		return $out;
	}
	
	/**
	 * Returns a filtered dictionary where any keys in the provided blacklist are filtered out from the result.
	 * 
	 * Also see FUNCTIONNAME for a more generic approach than whitelisting and blacklisting.
	 * 
	 * @param array $dict
	 * Dictionary to filter.
	 * 
	 * @param array $columns
	 * List of key names to disallow.
	 */
	public static function blacklist(array $dict, array $keys) {
		foreach ($keys as $key) if (\key_exists($key, $dict)) {
			unset($dict[$key]);
		}
		
		return $dict;
	}
	
	/**
	 * Allows you to split a single dict into two or more sub-dicts, based on an explicit list of key groups. 
	 * 
	 * Your input dict doesn't have to contain all (or any) of the keys listed in your $keyGroups. Any keys not present
	 * will simply not be present in the output either (so if none of the keys for a group exist in the input, you'll
	 * get an empty array for that group).
	 * 
	 * Example:
	 * 
	 * <code>
	 * $dict = [
	 * 		'a' => 1, 
	 * 		'b' => 2, 
	 * 		'c' => 3, 
	 * 		'd' => 4, 
	 * 		'e' => 5, 
	 * 		'f' => 6, 
	 * 		'g' => 7, 
	 * 		'h' => 8, 
	 * 		'i' => 9,
	 * ];
	 * 
	 * $keyGroups = [
	 * 		['a', 'b', 'c'],
	 * 		['d', 'e', 'f', 'g'],
	 * ];
	 * 
	 * // This will produce three dicts: 
	 * // - One with keys: a, b & c;
	 * // - One with keys: d, e, f & g;
	 * // - On with the rest of the key: h & i. 
	 * list($abc, $defg, $rest) = DictUtils::divide($dict, $keyGroups);
	 * </code>
	 * 
     * 
	 * @param array $dict
	 * A dict to divide.
	 * 
	 * @param array $keyGroups
	 * A list of key groups (each a list of strings, each the name of the key belonging to that group). Note that if
	 * a key is present in multiple groups, only the first group to include it will "get" the key in the resulting
	 * dictionaries.
	 * 
	 * @return array
	 * Returns a list of dicts, one dict for each group passed, and one more, for any keys that were not included in 
	 * any of the groups.
	 */
	public static function divide(array $dict, array $keyGroups) {
		$groups = [];
		
		foreach ($keyGroups as $i => $keys) {
			$group = [];
			
			foreach ($keys as $key) {
				if (\key_exists($key, $dict)) {
					$group[$key] = $dict[$key];
					unset($dict[$key]);
				}
			}
			
			$groups[] = $group;
		}
		
		$groups[] = $dict;
		return $groups;
	}
}