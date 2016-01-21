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
use Solver\Sidekick\SqlContext as SC;

/**
 * All methods return closures in encodeClause() format, as accepted by ExpressCodec::on*() methods, and provide
 * common rules which are typically applies in record codec handlers. 
 * 
 * TODO: Drop underscore suffix from method names when we drop PHP 5.x support.
 */
class ExpressCodecHandlers {
	/**
	 * A closure in encodeClause() format that requires exactly one, no less or more, of the specified fields in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function requireOne(...$fields) {
		return function (SC $sc, $in, & $out) use ($fields) {
			$count = 0;
			foreach ($fields as $field) if (isset($in[$field]) || key_exists($field, $in)) $count++;
			
			if ($count == 0) {
				self::error($sc, 'You must specify at least one', $fields);
			}
			
			if ($count > 1) {
				self::error($sc, 'You must specify at most one', $fields);
			}
		};
	}

	/**
	 * A closure in encodeClause() format that requires up to one, and no more, of the specified fields in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function requireOneOrNone(...$fields) {
		return function (SC $sc, $in, & $out) use ($fields) {
			$count = 0;
			foreach ($fields as $field) if (isset($in[$field]) || key_exists($field, $in)) $count++;
			
			if ($count > 1) {
				self::error($sc, 'You must specify at most one', $fields);
			}
		};
	}

	/**
	 * A closure in encodeClause() format that requires none of the specified fields be present in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function requireNone(...$fields) {
		return function (SC $sc, $in, & $out) use ($fields) {
			foreach ($fields as $field) if (isset($in[$field]) || key_exists($field, $in)) {
				self::error($sc, 'You must not specify any', $fields);
			}
		};
	}

	/**
	 * A closure in encodeClause() format that requires all or none of the specified fields be present in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function requireAllOrNone(...$fields) {
		return function (SC $sc, $in, & $out) use ($fields) {
			$count = 0;
			foreach ($fields as $field) if (isset($in[$field]) || key_exists($field, $in)) $count++;
			
			if ($count != 0 && $count != count($fields)) {
				self::error($sc, 'You must specify either all or none', $fields);
			}
		};
	}
	
	/**
	 * A closure in encodeClause() format, which requires at least one, or more, of the specified fields in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function requireAny(...$fields) {
		return function (SC $sc, $in, & $out) use ($fields) {
			foreach ($fields as $field) if (isset($in[$field]) || key_exists($field, $in)) return;
			
			self::error($sc, 'You must specify at least one', $fields);
		};
	}
	
	/**
	 * A closure in encodeClause() format, which requires all of the specified fields be present in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function requireAll(...$fields) {
		return function (SC $sc, $in, & $out) use ($fields) {
			foreach ($fields as $field) if (!isset($in[$field]) && !key_exists($field, $in)) {
				self::error($sc, 'You must specify all', $fields);
			}
		};
	}

	/**
	 * An alias for requireAll().
	 * 
	 * A closure in encodeClause() format that requires all of the specified fields be present in input.
	 * 
	 * @param string ...$fields
	 * Field names to check for existence (null counts as existence).
	 * 
	 * @return \Closure
	 */
	public static function require_(...$fields) {
		return self::requireAll(...$fields);
	}
	
	/**
	 * A closure in encodeClause() format that checks if the given field name is defined, if not, assigns the given
	 * value to the given column name.
	 * 
	 * @param string $field
	 * Field to check for existence (null counts as existence).
	 * 
	 * @param string|null $column
	 * Column to assign to. If null, assumes the column name is the same as the field name.
	 * 
	 * @param null|scalar|Expr $value
	 * A scalar value or an Expr instance (SQL expression) to assign as a default.
	 * 
	 * @throws \Exception
	 */
	public static function default_($field, $column, $value) {
		$column = $column ?: $field;
		
		return function (SC $sc, $in, & $out) use ($field, $column, $value) {
			var_dump('die another day'); die;
			if (!isset($in[$field]) && !key_exists($field, $in)) {
				$out[$column] = $value;
			}
		};
	}
	
	protected static function error(SqlContext $sc, $reason, $fields) {
		throw new \Exception($reason . ' of fields: ' . implode(', ', $fields) . ' in ' . CodecUtils::contextMaskToString($sc->getMask()) . '.');
	}
}