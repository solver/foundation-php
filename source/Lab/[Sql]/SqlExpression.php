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
 * Helpers for rendering arrays to several types of SQL expressions. Throws a generic exception on bad input formatting.
 * 
 * TODO: The code is fine, but it could use a few more checks for format strictness in places. Audit validation rules.
 */
class SqlExpression {
	/**
	 * Converts a dict/list/scalar to a JSON string.
	 * 
	 * This is exposed as it's a common operation with SQL-stored data, and so the right options are set on the encoder.
	 * 
	 * @param mixed $value
	 * 
	 * @param bool $stringInvalid
	 * Optional (default = false). Pass true if you want $data to be scanned recursively and the following invalid items
	 * are stripped from your data (the alternative is typically you get nothing). This option is useful when encoding
	 * data from foreign sources that may be only partially invalid.
	 * 
	 * - Invalid UTF8 sequences in strings are removed (the rest of the string is preserved).
	 * - Nan, Inf numbers are converted to null.
	 * 
	 * Depending on the nature of the data this may be desired (partially corrupted text from a web form), or not 
	 * (strictly formatted data, where stripping invalid parts is not an acceptable substitute).
	 * 
	 * @return string
	 */
	public static function toJson($value, $stripInvalid = false) {
		// TODO: apply this to strings recursively: iconv("UTF-8", "UTF-8//IGNORE", $text)
		
		if (!$stripInvalid) {
			return \json_encode($value, \JSON_UNESCAPED_UNICODE);
		} else {
			/*
			 * First try the quick route, assuming everything is ok. The JSON API inexplicably uses two different
			 * channels for emitting errors, so we monitor both.
			 */
			
			$hasErrors = false;
			
			\set_error_handler(function ($errno, $errstr) use (& $hasErrors) {
				$hasErrors = true;
			});
			
			$result = \json_encode($value, \JSON_UNESCAPED_UNICODE);
			
			\restore_error_handler();
			
			// Everything went fine.
			if (!$hasErrors && \json_last_error() == 0) return $result;
			
			/*
			 * Ok, go the slow route.
			 */
			
			$process = function (& $value) use (& $process) {
				if (\is_array($value) || \is_object($value)) {
					foreach ($value as & $subValue) {
						$process($subValue);
					}
				}
				
				elseif (\is_string($value)) {
					// And that's horrible, but it's what we have.
					$value = \html_entity_decode(\htmlentities($value, \ENT_IGNORE));
				}
				
				elseif (\is_nan($value) || is_infinite($value)) {
					$value = null;
				}
			};
			
			$process($value);
			
			// This time if it fails, we let it fail.
			return \json_encode($value, \JSON_UNESCAPED_UNICODE);
		}
	}
	
	/**
	 * Convets a JSON string back to a dict/list/scalar structure.
	 * 
	 * This is exposed as it's a common operation with SQL-stored data, and so the right options are set on the decoder.
	 * 
	 * @param string $json
	 * 
	 * @return array
	 */
	public static function fromJson($json) {
		return \json_decode($json, true);
	}
	
	/**
	 * Converts an SQL datetime expression to a UNIX timestamp.
	 * 
	 * @param null|string $datetime
	 * 
	 * @return null|int
	 * UNIX timestamp.
	 */
	static public function fromDatetime($datetime) {
		if ($datetime === null) return null;
		else return \strtotime($datetime);
	}
	
	/**
	 * Converts an SQL datetime expression to a UNIX timestamp.
	 * 
	 * @param null|string $datetime
	 * 
	 * @return null|int
	 * UNIX timestamp.
	 */
	static public function toDatetime($timestamp) {
		if ($timestamp === null) return null;
		else return \date('Y-m-d H:i:s', $timestamp);
	}
	
	/**
	 * Takes an array of bool SQL expressions and joins then with the given boolean operator (default AND). See
	 * renderBoolExpr().
	 * 
	 * @param SqlConnection $connection
	 * Database connection instance to quote/render against.
	 * 
	 * @param array $boolExprList
	 * A list of boolean expressions, each expression may be boolExpr array or SQL string expression.
	 * 
	 * @param array $operator
	 * Operator used to join each bool expression with the next one. Default AND. Supported operators: AND, OR, XOR.
	 * 
	 * @param array $subOperator
	 * Operator used to join the statements inside each bool expression. Default AND. Supported operators: AND, OR,
	 * XOR.
	 * 
	 * @return string
	 * SQL expression.
	 */
	static public function booleanMany(SqlConnection $connection, array $boolExprList, $operator = 'AND', $subOperator = 'AND') {		
		foreach ($boolExprList as & $boolExpr) {
			if (is_array($boolExpr)) {
				$boolExpr = self::boolean($boolExpr, $subOperator);			
			}
		}
		
		switch ($operator) {
			case 'AND':
			case 'OR':
			case 'XOR':
				return '(' . \implode(') ' . $operator . ' (', $boolExpr) . ')'; 
			default:
				throw new \Exception('Unknown logical operator "' . $operator. '".');
		}
	}
	
	
	/**
	 * Takes a boolean expression array and returns it rendered as an SQL string. The separate statements are joined via
	 * boolean AND, OR or XOR.
	 * 
	 * @param SqlConnection $connection
	 * Database connection instance to quote/render against.
	 * 
	 * @param array $boolExpr
	 * A dict of rules for building a boolean SQL expression. Each rule can be in one of these formats:
	 * 
	 * # Short format: {identifier} => {value}.
	 * Matches the row dict format, and interpretes as the "equals" operator : `col` = "val".
	 * 
	 * # Custom match format: {identifier} => [{operator}, {value}].
	 * 
	 * You can use these operators: = (==), != (<>), >, <, >=, <=, LIKE, !LIKE, REGEXP, !REGEXP.
	 * 
	 * The operators listed in parens above are aliases (same semantics). The "=" operator supports null values. 
	 * 
	 * # Custom set match format: {identifier} => [{operator}, [{value1}, {value2}, ...]].
	 * 
	 * You can use these operators: IN, !IN, BETWEEN, !BETWEEN (the last two require exactly 2 values).
	 * 
	 * @param array $operator
	 * Boolean logic operator to use when joining the fragments, default AND. Supported operators: AND, OR, XOR.
	 * 
	 * @return string
	 * An SQL expression.
	 */
	static public function boolean(SqlConnection $connection, array $boolExpr, $operator = 'AND') {		
		$exprList = array();
		
		if(!is_array($boolExpr)) {
			throw new \Exception('Bad expression format.');
		}
		
		foreach($boolExpr as $col => $rule) {
			
			if(!\is_array($rule)) { // Simple equals.
				
				$exprList[] = $connection->quoteIdentifier($col) . ($rule === null ? ' IS NULL' : ' = ' . $connection->quoteValue($rule));
				
			} else {
				
				list($type, $val) = $rule;
				
				if (!\is_scalar($type)) {
					throw new \Exception('Bad expression operator format.');					
				}
						
				switch($type) {
					case '=': 
					case '==':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ($val === null ? ' IS NULL' : ' = ' . $connection->quoteValue($val));
						break;
						
					case '!=':
					case '<>':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ($val === null ? ' IS NOT NULL' : ' <> ' . $connection->quoteValue($val));
						break;							
					case '>':
					case '<':
					case '>=':
					case '<=':
					case 'LIKE':
					case 'REGEXP':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' ' . $type . ' ' . $connection->quoteValue($val);
						break;
						
					case '!LIKE':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' NOT LIKE ' . $connection->quoteValue($val);
						break;
						
					case '!REGEXP':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' NOT REGEXP ' . $connection->quoteValue($val);
						break;
						
					case 'IN':
						if (!is_array($val)) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' ' . $type . ' (' . \implode(',', $connection->quoteValue($val)) . ')';
						break;
						
					case '!IN':
						if (!is_array($val)) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' NOT IN (' . \implode(',', $connection->quoteValue($val)) . ')';
						break;
						
					case 'BETWEEN':
						if (!is_array($val) || \count($val) != 2) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' ' . $type . ' ' . $connection->quoteValue($val[0]) . ' AND ' . $connection->quoteValue($val[1]);
						break;
						
					case '!BETWEEN':
						if (!is_array($val) || \count($val) != 2) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $connection->quoteIdentifier($col) . ' NOT BETWEEN ' . $connection->quoteValue($val[0]) . ' AND ' . $connection->quoteValue($val[1]);
						break;
						
					default: 
						throw new \Exception('Unknown operator "' . $type . '".');
				}
			}
		}			
		
		switch ($operator) {
			case 'AND':
			case 'OR':
			case 'XOR':
				return \implode(' ' . $operator . ' ', $exprList);
			default:
				throw new \Exception('Unknown logical operator ' . $operator);
		}
		
	}
	
	
	/**
	 * Renders an ORDER BY expression from a hashmap in format: {colName} => "ASC" / "DESC".
	 * 
	 * @param SqlConnection $connection
	 * Database connection instance to quote/render against.
	 * 
	 * @param array $orderExpr
	 * 
	 * @return string
	 */
	static public function orderBy(SqlConnection $connection, array $orderExpr) {
		$expr = array();
			
		foreach ($orderExpr as $col => $mode) {
			if($mode === 'ASC' || $mode === 'DESC') {
				$expr[] =  $connection->quoteIdentifier($col) . ' ' . $mode;
			} else {
				throw new \Exception('Invalid order mode "' . $mode . '" (expected "ASC" or "DESC").');
			}
		}
		
		return implode(', ', $expr);
	}
	
	
	/**
	 * Renders a list of quoted identifiers into a comma delimited string. Aliases ("x AS y") can be specified if you
	 * pass a list of two strings instead of a string.
	 * 
	 * @param SqlConnection $connection
	 * Database connection instance to quote/render against.
	 * 
	 * @param array $identExpr
	 * A list of identifiers (tables, columns). Each identifier is either a string, or a list of two elements (true
	 * name, alias).
	 * 
	 * For example ['foo', 'bar', ['baz', 'qux']] will render produce `foo`, `bar`, `baz` AS `qux`.
	 * 
	 * @return string
	 * An SQL expression.
	 */
	static public function identList(SqlConnection $connection, $identExpr) {		
		// Taking advantage of the recursive nature of quoteIdentifier() here.
		$identExpr = $connection->quoteIdentifier($identExpr);
		
		foreach ($identExpr as & $ident) {
			$ident = $ident[0] . ' AS ' . $ident[1];
		}
		unset($ident);
		
		return implode(', ', $identExpr);		
	}
}