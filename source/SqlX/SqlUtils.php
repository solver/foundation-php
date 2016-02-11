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
namespace Solver\SqlX;

use Solver\Sql\PdoMysqlSession;

/**
 * Common tasks performed on an SQL connection.
 * 
 * IMPORTANT: Only MySQL and SQLite connections are supported at the moment.
 * 
 * TODO: Replace PdoMysqlSession hints with SqlSession hints (once the interfaces are defined).
 * TODO: Validate the given connection is MySQL or SQLite.
 * TODO: This class will eventually be refactored into something better.
 */
class SqlUtils {
	/**
	 * TODO: Extract as a standalone tool which can build a set of expressions from scalars and arrays from inline
	 * hints like ->add("SELECT @asList", [['foo', 'FOO'], 'bar', 'baz', ['bam' => 'BAM']]).
	 * 
	 * Replaces question marks in the $sql string with encoded SQL value literals.
	 * 
	 * Note this isn't a prepared statement
	 * API, the engine just looks for question marks without analyzing the query structure (i.e. if your $sql string
	 * contains a question mark within a string literal, it'll get replaced as well).
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Connection against which to execute the operation.
	 * 
	 * @param string $sql
	 * SQL string containing one more question marks "?".
	 * 
	 * @param array $values
	 * A list of values to replace in the SQL string. 
	 * 
	 * @return string
	 */
	public static function encodeInto(PdoMysqlSession $sqlSess, $sql, array $values) {
		foreach ($values as & $value) $value = $sqlSess->encodeValue($value);
		unset($value);
		
		$sql = \explode('?', $sql);
		$ce = \count($sql);
		
		if ($ce != \count($values) + 1) {
			throw new \Exception('The number of values passed does not match the number of replace marks in the expression.');
		}
		
		$out = \reset($sql) . \reset($values) . \next($sql);
		
		for ($i = 2; $i < $ce; $i++) {
			$out .= \next($values).$sql[$i];
		}	

		return $out;
	}
	
	/**
	 * Inserts a single row.
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Connection against which to execute the operation.
	 * 
	 * @param string $table
	 * Table name (will be encoded as identifier).
	 * 
	 * @param array $row
	 * Hashmap representing row (automatically encoded).
	 * 
	 * @param bool $replace
	 * Default false. If true, builds a REPLACE query (INSERT or UPDATE) instead of an INSERT query.
	 */
	public static function insert(PdoMysqlSession $sqlSess, $table, array $row, $replace = false) {
		self::insertMany($sqlSess, $table, array($row), false, $replace);
	}

	/**
	 * Inserts a list of new rows into a table.
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Connection against which to execute the operation.
	 * 
	 * @param string $table
	 * Table name (will be encoded as identifier).
	 * 
	 * @param array $rows
	 * A list of rows (automatically encoded). Each row is a dict formatted [colName => value, colName => value, ...].
	 * 
	 * @param bool $extended
	 * Optional (default = false). If true, the engine will attempt to insert all passed rows in a single extended
	 * insert query. This requires that all rows to be inserted have the same columns.
	 * 
	 * @param bool $replace
	 * Default false. If true, builds a REPLACE query (INSERT or UPDATE) instead of an INSERT query.
	 */
	public static function insertMany(PdoMysqlSession $sqlSess, $table, array $rows, $extended = false, $replace = false) {
		if (!$rows) return;
		
		$tblQ = $sqlSess->encodeName($table);
		
		if (!$extended) {		
			// TODO: Wrap in a transaction if count($rows) > 1 (once we have nested transactions again).	
			for($i = 0, $max = \count($rows); $i < $max; $i++) {
				$row = [];
				foreach ($rows[$i] as $k => $v) $row[$sqlSess->encodeName($k)] = $sqlSess->encodeValue($v);
				if ($replace) $type = 'REPLACE'; else $type = 'INSERT';
				$sql = $type . ' INTO ' . $tblQ . ' (' . \implode(',', \array_keys($row)).') VALUES (' . \implode(',', $row) . ')';
				$sqlSess->execute($sql);
			}
		}
		
		// Single extended insert (cols specified for each row should match).
		else {
			$cols = \array_keys($rows[0]);
			$colsQ = [];
			foreach ($cols as $col) $colsQ[] = $sqlSess->encodeName($col);
			
			// When imploded, forms the VALUES part of the query.
			$valSeq = array();
			
			for($i = 0, $max = \count($rows); $i < $max; $i++) {
				$row = $rows[$i];
				
				foreach ($row as $k => $v) {
					$row[$k] = $sqlSess->encodeValue($rows[$i][$k]);
				}
				
				$vals = array();
				
				// TRICKY: we're looping the column names from the first row, and retrieving the values in that order,
				// as even if all rows have the same columns specified, they may not necessarily be written in the same
				// order in the PHP array.
				foreach($cols as $col) {			
					if (\array_key_exists($col, $row)) {
						$vals[] = $row[$col];
					} else {
						throw new \Exception('Column "' . $col . '" expected but not found in row number ' . $i . '.');
					}
				}
				
				$valSeq[] = '(' . \implode(',', $vals) . ')';
			}
			
			if ($replace) $type = 'REPLACE'; else $type = 'INSERT';
			$sql = $type . ' INTO '.$tblQ.' (' . implode(',', $colsQ) . ') VALUES ' . implode(',', $valSeq);

			$sqlSess->execute($sql);
		}
	}
		
	/**
	 * Updates a table row, matching it by the column(s) specified as a primary key.
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Connection against which to execute the operation.
	 * 
	 * @param string $table
	 * Table name (will be encoded as identifier).
	 * 
	 * @param string|array $pkCols
	 * String column name of the primary key (or a list of column names for composite keys).
	 * 
	 * @param array $row
	 * A dict representing a row. The columns used as PK must be present, but it's not a requirement to pass all of the
	 * remaining cols in the table, only those to be updated.
	 */
	public static function updateByPrimaryKey(PdoMysqlSession $sqlSess, $table, $pkCols, array $row) {	
		return self::updateByPrimaryKeyMany($sqlSess, $table, $pkCols, array($row));
	}	
	
	/**
	 * Executes updates for all passed rows, matching them by the column(s) specified as id (primary key).
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Connection against which to execute the operation.
	 * 
	 * @param string $table
	 * Table name (will be encoded as identifier).
	 * 
	 * @param string $pkCols
	 * String column name of the primary key (or array of column names for composite keys).
	 * 
	 * @param array $rows
	 * Array of hashmaps representing a row each. The columns used as id must be specified, but it's not a requirement
	 * to have all other columns.
	 */
	public static function updateByPrimaryKeyMany(PdoMysqlSession $sqlSess, $table, $pkCols, $rows) {
		if (empty($rows)) return;
		
		$tblQ = $sqlSess->encodeName($table);
		
		if (is_array($pkCols)) {
			foreach ($pkCols as $i => $pkCol) {
				$pkColsQ[$i] = $sqlSess->encodeName($pkCols[$i]);	
			}
		} else {
			$pkColsQ = $sqlSess->encodeName($pkCols);
		}
		
		if (\is_array($pkCols)) {
			foreach ($rows as $rk => $row) {
				$row = $sqlSess->encodeValue($row);
				
				$setArr = array();
				$whereArr = array();
				
				// TRICKY: above we escaped the id colnames in $pkColsQ so we need the keys (idk) to fetch them from the actual id-s.
				foreach ($pkCols as $pkCol => $pkVal) {
					if (!isset($row[$pkVal])) throw new \Exception('Primary key column "' . $pkVal . '" missing in an update row at index "' . $rk . '".');
					$whereArr[] = $pkColsQ[$pkCol].' = '.$row[$pkVal];
					unset($row[$pkVal]);
				}
				
				foreach ($row as $rk => $rv) {
					$setArr[] = $sqlSess->encodeName($rk).' = '.$rv;
				}
				
				if (!$setArr) return;
				
				$q = 'UPDATE '.$tblQ.' SET '.$setArr(',', $setArr).' WHERE '.implode(',', $whereArr);
				
				$sqlSess->execute($q);
			}
		} else {
			foreach ($rows as $rk => $row) {
				foreach ($row as $i => $val) {
					$row[$i] = $sqlSess->encodeValue($val);
				}
				
				$setArr = array();
				
				if (!isset($row[$pkCols])) throw new \Exception('Identifier "' . $pkCols . '" missing in an update row at index "' . $rk . '".');
				$where = $pkColsQ . ' = ' . $row[$pkCols];
				unset($row[$pkCols]);
				
				foreach ($row as $rk => $rv) {
					$setArr[] = $sqlSess->encodeName($rk) . ' = ' . $rv;
				}
				
				if (!$setArr) return;
				
				$q = 'UPDATE ' . $tblQ . ' SET ' . implode(',', $setArr) . ' WHERE ' . $where;
				
				$sqlSess->execute($q);
			}			
		}
	}	
	
	/**
	 * Blanks the table. Optionally will reset the autoIncrement counter.
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Connection against which to execute the operation.
	 * 
	 * @param string $table
	 * Table name (will be encoded as identifier).
	 * 
	 * @param bool $resetAutoIncrement
	 * Whether to reset autoincrement to 1.
	 */
	public static function truncate(PdoMysqlSession $sqlSess, $table, $resetAutoIncrement = false) {
		$tblQ = $sqlSess->encodeName($table);
		
		$sqlSess->execute('TRUNCATE TABLE ' . $tblQ);
		
		if ($resetAutoIncrement) {
			$sqlSess->execute('ALTER TABLE ' . $tblQ . ' AUTO_INCREMENT = 1');
		}
	}
	

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
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
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
	static public function booleanMany(PdoMysqlSession $sqlSess, array $boolExprList, $operator = 'AND', $subOperator = 'AND') {		
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
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
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
	static public function boolean(PdoMysqlSession $sqlSess, array $boolExpr, $operator = 'AND') {		
		$exprList = array();
		
		if(!is_array($boolExpr)) {
			throw new \Exception('Bad expression format.');
		}
		
		foreach($boolExpr as $col => $rule) {
			
			if(!\is_array($rule)) { // Simple equals.
				
				$exprList[] = $sqlSess->encodeName($col) . ($rule === null ? ' IS NULL' : ' = ' . $sqlSess->encodeValue($rule));
				
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
						$exprList[] = $sqlSess->encodeName($col) . ($val === null ? ' IS NULL' : ' = ' . $sqlSess->encodeValue($val));
						break;
						
					case '!=':
					case '<>':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $sqlSess->encodeName($col) . ($val === null ? ' IS NOT NULL' : ' <> ' . $sqlSess->encodeValue($val));
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
						$exprList[] = $sqlSess->encodeName($col) . ' ' . $type . ' ' . $sqlSess->encodeValue($val);
						break;
						
					case '!LIKE':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $sqlSess->encodeName($col) . ' NOT LIKE ' . $sqlSess->encodeValue($val);
						break;
						
					case '!REGEXP':
						if (!\is_scalar($val)) {
							throw new \Exception('Bad expression value format.');
						}
						$exprList[] = $sqlSess->encodeName($col) . ' NOT REGEXP ' . $sqlSess->encodeValue($val);
						break;
						
					case 'IN':
						if (!is_array($val)) {
							throw new \Exception('Invalid value list format.');
						}
						
						if (!$val) {
							$exprList[] = '1 = 0';
						} else {
							$valEn = [];
							foreach ($val as $subVal) $valEn[] = $sqlSess->encodeValue($subVal);
							$exprList[] = $sqlSess->encodeName($col) . ' ' . $type . ' (' . \implode(',', $valEn) . ')';
						}
						break;
						
					case '!IN':
						if (!is_array($val)) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $sqlSess->encodeName($col) . ' NOT IN (' . \implode(',', $sqlSess->encodeValue($val)) . ')';
						break;
						
					case 'BETWEEN':
						if (!is_array($val) || \count($val) != 2) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $sqlSess->encodeName($col) . ' ' . $type . ' ' . $sqlSess->encodeValue($val[0]) . ' AND ' . $sqlSess->encodeValue($val[1]);
						break;
						
					case '!BETWEEN':
						if (!is_array($val) || \count($val) != 2) {
							throw new \Exception('Invalid value list format.');
						}
						$exprList[] = $sqlSess->encodeName($col) . ' NOT BETWEEN ' . $sqlSess->encodeValue($val[0]) . ' AND ' . $sqlSess->encodeValue($val[1]);
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
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Database connection instance to quote/render against.
	 * 
	 * @param array $orderExpr
	 * 
	 * @return string
	 */
	static public function orderBy(PdoMysqlSession $sqlSess, array $orderExpr) {
		$expr = array();
			
		foreach ($orderExpr as $col => $mode) {
			if($mode === 'ASC' || $mode === 'DESC') {
				$expr[] =  $sqlSess->encodeName($col) . ' ' . $mode;
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
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
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
	static public function identList(PdoMysqlSession $sqlSess, $identExpr) {		
		// Taking advantage of the recursive nature of encodeName() here.
		$identExpr = $sqlSess->encodeName($identExpr);
		
		foreach ($identExpr as & $ident) {
			$ident = $ident[0] . ' AS ' . $ident[1];
		}
		unset($ident);
		
		return implode(', ', $identExpr);		
	}
	
	/**
	 * Renders an IN expression, complete with the encoded column name, like this:
	 * 
	 * `foo` IN (1, 2, 3)
	 * 
	 * @param \Solver\Sql\PdoMysqlSession $sqlSess
	 * Database connection instance to quote/render against.
	 * 
	 * @param string $colName
	 * Column name (unencoded, plain text).
	 * 
	 * @param array $values
	 * list<scalar>; Values to test against.
	 * 
	 * @return string
	 * An SQL expression.
	 */
	public static function in(PdoMysqlSession $sqlSess, $colName, array $values) {
		if (!$values) return '1 = 0';
		
		$valuesQ = [];
		
		foreach ($values as $value) {
			$valuesQ[] = $sqlSess->encodeValue($value);
		}
		
		return $sqlSess->encodeName($colName) . ' IN (' . implode(', ', $valuesQ) . ')';
	}
	
	/**
	 * Returns a full row by id (primary key column named "id"; a common case).
	 * 
	 * TODO: Add FOR UPDATE flag.
	 * 
	 * @param PdoMysqlSession $sqlSess
	 * @param string $tableName
	 * @param string $id
	 * @return array
	 */
	public static function getById(PdoMysqlSession $sqlSess, $tableName, $id) {
		$tableEn = $sqlSess->encodeName($tableName);
		$sql = self::encodeInto($sqlSess, "SELECT * FROM $tableEn WHERE id = ?", [$id]);
		return $sqlSess->query($sql)->getOne();
	}
	
	/**
	 * Check a row id for existence (primary key column named "id"; a common case).
	 * 
	 * TODO: Add FOR UPDATE flag.
	 * 
	 * @param PdoMysqlSession $sqlSess
	 * @param string $tableName
	 * @param string $id
	 * @return array
	 */
	public static function hasId(PdoMysqlSession $sqlSess, $tableName, $id) {
		$tableEn = $sqlSess->encodeName($tableName);
		$sql = self::encodeInto($sqlSess, "SELECT id FROM $tableEn WHERE id = ?", [$id]);
		return (bool) $sqlSess->query($sql)->getOne();
	}
}