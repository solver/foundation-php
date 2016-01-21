<?php
namespace Solver\Sidekick;

use Solver\Sidekick\SqlContext as SC;

class CodecUtils {
	// TODO: Move this to CodecUtils (useful for users, not just internally) and rename it CodecUtils.
	public static function contextMaskToString($mask) {
		$stmtType = $mask & SC::C_ALL_STATEMENTS;
		$clauseType = $mask & SC::C_ALL_CLAUSES;
		
		static $statement = [
			SC::C_INSERT_STATEMENT => 'INSERT',
			SC::C_QUERY_STATEMENT => 'QUERY',
			SC::C_UPDATE_STATEMENT => 'UPDATE',
			SC::C_DELETE_STATEMENT => 'DELETE',
		];
		
		static $clause = [
			SC::C_SELECT => 'SELECT',
			SC::C_WHERE => 'WHERE',
			SC::C_FROM => 'FROM',
			SC::C_JOIN => 'JOIN',
			SC::C_GROUP => 'GROUP',
			SC::C_HAVING => 'HAVING',
			SC::C_ORDER => 'ORDER',
			SC::C_VALUES => 'VALUES',
			SC::C_SET => 'SET',
		];
		
		return self::bitsToStrings($mask, $statement, 'statement') . ', ' . self::bitsToStrings($mask, $clause, 'clause');
	}
	
	protected static function bitsToStrings($mask, $stringMap, $type) {
		$set = [];
		
		foreach (range(0, 31) as $bit) {
			$flag = 1 << $bit;
			if (isset($stringMap[$flag]) && ($mask & $flag)) $set[] = $stringMap[$flag];
		}
		
		return implode('/', $set) . ' ' . $type . (count($set) != 1 ? 's' : '');
	}
}