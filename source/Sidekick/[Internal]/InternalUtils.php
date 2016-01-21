<?php
namespace Solver\Sidekick;

class InternalUtils {
	/**
	 * Positional values in the input (integer keys) are treated as $value => null (as "tags" with empty value).
	 *  
	 * TODO: Remove this and replace with more specific schemes we use in Sidekick and codecs.
	 * 
	 * @param array $array
	 * 
	 * @return array
	 * 
	 * @deprecated
	 */
	public static function getPositionalAsTags($array) {
		foreach ($array as $key => $val) {
			if (is_int($key)) {
				unset($array[$key]);
				$array[$val] = null;
			}
		}
		
		return $array;
	}
}