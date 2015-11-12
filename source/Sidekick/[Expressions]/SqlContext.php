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
namespace Solver\Sidekick;

use Solver\Sql\SqlSession;

class SqlContext {
	const S_INSERT = 1 << 0;
	const S_QUERY = 1 << 1;
	const S_UPDATE = 1 << 2;
	const S_DELETE = 1 << 3;
	
	// TODO: Scattered here are comments how clauses interpret $fields dicts, for example foo => bar. Flesh out and document properly.   
	const C_SELECT = 1 << 8; // SELECT `bar` AS `foo` or SELECT `foo` (if bar is null0 or SELECT (expr) AS `foo` if expr.
	const C_WHERE = 1 << 9; // WHERE `foo` = "bar"
	const C_GROUP = 1 << 10; // (if bar is null) GROUP BY `foo`  (if expression) GROUP BY expression
	const C_HAVING = 1 << 11; // -- as WHERE ---
	const C_ORDER = 1 << 12; // foo => null is ORDER BY foo, can be string ASC DESC, or expression to order by it
	const C_VALUES = 1 << 13; // fieldname = value or expression
	const C_SET = 1 << 14; // fieldname = value or expression
	const C_JOIN = 1 << 15; // Expressons only, no subject. This clause is only ever executed in P_DERIVE.
	
	const S_MASK = self::S_INSERT|self::S_QUERY|self::S_UPDATE|self::S_DELETE;
	const C_MASK = self::C_SELECT|self::C_WHERE|self::C_GROUP|self::C_HAVING|self::C_ORDER|self::C_VALUES|self::C_SET|self::C_JOIN;
	
	const FULL_MASK = self::S_MASK|self::C_MASK;
	
	protected $sess, $mask, $allowJoins, $usedJoins;
	
	public function __construct(SqlSession $sess, & $mask, $allowJoins, & $usedJoins) {
		$this->sess = $sess;
		
		// We assign by ref. so the caller can keep the values up-to-date from their references (performance optim.).
		$this->mask = & $mask;
		$this->allowJoins = $allowJoins;
		$this->usedJoins = & $usedJoins;
	}
	
	public function getMask() {
		return $this->mask;
	}
	
	/**
	 * Same as SqlSession::encodeValue().
	 * 
	 * TODO: Document better.
	 */
	public function encodeValue($value) {
		return $this->sess->encodeValue($value);
	}
	
	/**
	 * Same as SqlSession::encodeIdent(), but the second parameter has a more narrow and specific meaning. It serves 
	 * to detect use of columns from joins, so always pass your column identifiers through this method, both for 
	 * security, and to activate joins.
	 * 
	 * Joins get activated, when an identifier with a single dot is passed for encoding (with second param as true). Do
	 * not pass in table alias and column name separately, or the join reference won't be detected. I.e.:
	 * 
	 * - Do this:	$ctx->encodeIdent('foo.bar', true);
	 * - Not this:	$ctx->encodeIdent('foo') . '.' . $ctx->encodeIdent('bar');
	 * 
	 * References to "this" table alias (the root table) are stripped automatically in contexts that don't allow joins,
	 * and unqualified identifiers automatically refer to the root table.
	 * 
	 * TODO: Document better.
	 * 
	 * @param string $ident
	 * 
	 * @param bool $isTableDotColumn
	 * - If true: if you pass an identifier with no dots, it's treated as a column reference. With one dot, it's treated as a 
	 * table.column reference.
	 * - If false: dots are encoded as a part of the identifier (a literal character).
	 * 
	 * @return string
	 */
	public function encodeIdent($ident, $isTableDotColumn = false) {
		if ($isTableDotColumn) {
			// TODO: Optimize?
			$segs = explode('.', $ident);
			$count = count($segs);
			if ($count == 1) return $this->sess->encodeIdent($ident);
			if ($count == 2) {
				$table = $segs[0];
				if ($this->allowJoins) {
					if ($table !== 'this') $this->usedJoins[$table] = true;
					return $this->sess->encodeIdent($ident, true);
				} else {
					// TODO: More specific error (move contextForDisplay() from Sidekick to this class?)
					if ($table !== 'this') throw new \Exception('Using columns from a table join in ' . InternalUtils::contextMaskToString($this->mask) . ', which doesn\' t allow joins.');
					return $this->sess->encodeIdent($segs[1]);
				}
			}
			throw new \Exception('Given identifier has more than two dot-delimited segments. One or two expected.');
		} else {
			return $this->sess->encodeIdent($ident);
		}
	}
}