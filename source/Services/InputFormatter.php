<?php
namespace Solver\Services;

class InputFormatter {
	/**
	 * Checks if $input the $data input contains root-level keys beginning with "@", which shouldn't be allowed for 
	 * remote input, as it's reserved for metadata keys. Metadata should be inserted by the caller after this check.
	 * 
	 * @param array $input
	 * 
	 * @return bool
	 * True if there are metadata keys, false if not.
	 */
	public static function hasMetadata($input) {
		foreach ($input as $key => $value) {
			if ($key !== '' && $key[0] === '@') {
				return true;
			}
		}
		
		return false;
	}
}