<?php
namespace Solver\Sidekick;

use Solver\Sidekick\SqlContext as SC;

class InternalUtils {
	/**
	 * Positional values in the input (integer keys) are treated as $value => null (as "tags" with empty value).
	 *  
	 * @param array $array
	 * 
	 * @return array
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
	
	public static function contextMaskToString($mask) {
		$stmtType = $mask & SC::S_MASK;
		$clauseType = $mask & SC::C_MASK;
		
		static $labels = [
			SC::S_INSERT => 'INSERT statement',
			SC::S_QUERY => 'QUERY statement',
			SC::S_UPDATE => 'UPDATE statement',
			SC::S_DELETE => 'DELETE statement',  
			SC::C_SELECT => 'SELECT clause',
			SC::C_WHERE => 'WHERE clause',
			SC::C_GROUP => 'GROUP clause',
			SC::C_HAVING => 'HAVING clause',
			SC::C_ORDER => 'ORDER clause',
			SC::C_VALUES => 'VALUES clause',
			SC::C_SET => 'SET clause',
			SC::C_JOIN => 'JOIN clause',
		];
		
		return $labels[$stmtType] . ', ' . $labels[$clauseType];
	}
}