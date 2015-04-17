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
namespace Solver\Toolbox;

/**
 * Utilities for working with & processing PHP arrays representing a tuple. 
 * 
 * A tuple is a zero-based, indexed, dense array (no index "holes"). Unlike lists, it has fixed length, and heterogenous
 * items (an item at a specific index has a specific type).
 * 
 * Tip: use PHP's list() for destructuring tuples into variables.
 */
class TupleUtils {
	/**
	 * Returns a dict (assoc. array) by replacing indexes with the given keys. If you want to skip a tuple index, pass
	 * null for its key. You can also skip trailing tuple items by not providing keys for them when calling this 
	 * function (see the example). If you provide more keys than you have items in the tuple, the dictionary keys will
	 * not be present in the resulting dict.
	 *  
	 * <code>
	 * $tuple = [1, 2, 3, 4];
	 * 
	 * $dict = TupleUtils::dictFrom($tuple, 'a');
	 * 
	 * var_export($dict); // ['a' => 1];
	 * 
	 * // Key 'c' won't map. No 5th tuple item, no error.
	 * $dict = TupleUtils::dictFrom($tuple, 'a', null, 'b', null, 'c'); 
	 * 
	 * var_export($dict); // ['a' => 1, 'b' => 3];
	 * </code>
	 * 
	 * @param array $dict
	 * Tuple to map.
	 * 
	 * @param array ...$keys
	 * List of key names for mapping the tuple to a dictionary. Null or a skipped trailing key (or keys) are interpreted
	 * as "do not map this index to a key in the dictionary".
	 * 
	 * @return array
	 * A dict mapping of the given tuple.
	 */
	public static function toDict(array $tuple, ...$keys) {
		$out = [];
		
		foreach ($keys as $i => $key) {
			if (\key_exists($key, $tuple)) {
				$out[$key] = $tuple[$i];
			} else {
				$out[] = null;
			}
		}
		
		return $out;
	}
}