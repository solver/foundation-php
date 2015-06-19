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
namespace Solver\Sql;

/**
 * Represents an SQL connection/session.
 * 
 * TODO: Improve PHPDoc.
 */
interface SqlConnection {
	public function open();
	
	public function close();
	
	/**
	 * Runs a query, returns a statement.
	 * 
	 * @param string $sql
	 * SQL query string to execute.
	 * 
	 * @return SqlResultSet
	 */
	public function query($sql);
	
	public function execute($sql);
		
	public function getLastInsertId();
	
	/**
	 * Quotes a value for use as an SQL literal. It has the following enhancements over the typical quote() method in
	 * other layers:
	 * 
	 * - PHP null will convert to SQL NULL.
	 * - Non-negative integers and strings which represent valid non-negative integers (unsigned 64-bit range) won't be
	 * quoted, so they can be used, for ex. with LIMIT and OFFSET. Note that a string with leading zeroes is not 
	 * considered a valid integer in this context.
	 * - Passing an array will return the array with every value in it encoded.
	 * 
	 * @param array|string|float|int|null $value
	 * A value, or an array of values to encode. The function works recursively, so if you have, say, an array of
	 * strings inside the array you pass, it'll all get encoded.
	 * 
	 * @return string
	 * Quoted value.
	 */
	public function encodeValue($value);
	
	/**
	 * Encodes a string (or an array of strings) as SQL identifiers. This is only required for untrusted identifier, and
	 * identifiers containing illegal identifier characters (say, a dot).
	 * 
	 * @param array|string $identifier
	 * A value, or a list of values to encode. The function works recursively, so if you have, say, an array of strings
	 * inside the array you pass, it'll all get encoded.
	 * 
	 * @param bool $respectDots
	 * Optional (default = false). If false, dots in the identifier are treated as a part of the name (no special
	 * meaning). If true, dots will be interpreted as separators (so you can encode, say, table.column strings in one
	 * call).
	 * 
	 * @return string
	 * Quoted value.
	 */
	public function encodeIdent($identifier, $respectDots = false);
	
	/**
	 * Encodes every key in the dict as an identifier, and every value as an SQL value literal, i.e. [ident => value].
	 * 
	 * TODO: Recursively encode sub-dicts in the same manner (right now not the case).
	 * 
	 * @param array $row
	 * A dict where the keys will be treated as SQL identifiers, and the values will be treated as SQL value literals.
	 * 
	 * @param bool $respectDots
	 * See encodeIdent() for details.
	 * 
	 * @return array
	 * Quoted dict.
	 */
	public function encodeRow(array $row, $respectDots = false);
	
	/**
	 * Calls the given closure in an SQL transaction.
	 * 
	 * Note: if you want to skip the optional parameter $isolation, you can directly pass $function as a first
	 * parameter, i.e. those two calls are equivalent:
	 * 
	 * <code>
	 * $conn->callInTransaction(null, function () { ... });
	 * $conn->callInTransaction(function () { ... });
	 * </code>
	 * 
	 * @param string $isolation
	 * Optional (default = null, which is treated as "REPEATABLE READ"). An SQL-compatible string setting the isolation
	 * level of the transaction.
	 * 
	 * @param \Closure $function
	 * Function to call within an SQL transaction. These condition will result in transaction rollback instead of a 
	 * commit:
	 * 
	 * - Returning boolean false from the function. Note, this doesn't apply for "falsey" values like null, 0 or empty
	 * string, but strictly boolean false.
	 * - Any uncaught exception leaving the function.
	 */
	public function transactional($isolation = null, $function = null);
}