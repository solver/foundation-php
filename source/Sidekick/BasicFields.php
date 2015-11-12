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

use Solver\SqlX\SqlUtils;
use Solver\Sidekick\SqlContext as SC;

// TODO: Drop "Field" suffix in methods when we're PHP7+ and we no longer risk PHP keyword collision.
class BasicFields {
	public static function jsonField($field, $column = null) {
		return self::simpleField($field, $column, 
			function ($value) { return json_encode($value); },
			function ($value) { return json_decode($value, true); }
		);
	}
	
	public static function boolField($field, $column = null) {
		return self::simpleField($field, $column, 
			function ($value) { return $value ? 1 : 0; },
			function ($value) { return (bool) $value; }
		);
	}
	
	public static function stringField($field, $column = null) {
		$transform = function ($value) { return (string) $value; };
		return self::simpleField($field, $column, $transform, $transform);
	}
	
	public static function intField($field, $column = null) {
		$transform = function ($value) { return (int) $value; };
		return self::simpleField($field, $column, $transform, $transform);
	}
	
	public static function floatField($field, $column = null) {
		$transform = function ($value) { return (float) $value; };
		return self::simpleField($field, $column, $transform, $transform);
	}
	
	public static function numberField($field, $column = null) {
		$transform = function ($value) { return +$value; };
		return self::simpleField($field, $column, $transform, $transform);
	}
	
	/**
	 * Timestamp integer on PHP's side, DATETIME string on SQL's side.
	 */
	public static function timestampFieldFromDateTime($field, $column = null) {
		return self::simpleField($field, $column, 
			function ($value) { return SqlUtils::toDatetime($value); },
			function ($value) { return SqlUtils::fromDatetime($value); }
		);
	}
	
	// TODO: We need to figure out how to support type filters (int, string, bool, etc.) on each sub-field without 
	// copy pasting the handlers from the other col methods complicating the input format, or going stringly typed with
	// labels. Either export the codec methods as separate methods, or figure out something.
	public static function compositeField($name, $internalPrefix, $fields) {
		// TODO: Implement.
		throw new \Exception('Not implemented.');
	}
	
	public static function sub
	
	// TODO: document that it's readonly and $columns supports three formats in same array:
	// - Basic string is column, read as is (already supported)
	// - List of strings is multiple columns.
	// - Dict of ["foo" => null] is same as list like ["foo"], latter is just shorthand.
	// - Dict of ["foo" => "bar"] is column "bar" read as alias "foo" (bar AS foo)
	// - Dict of ["foo" => new Expr()] is SQL expression read as alias "foo".
	public static function derivedField($field, $columns, $deriveFromRow) {
		// Normalize.
		if (is_array($columns)) {
			foreach ($columns as $key => $val) {
				if (is_int($key)) {
					$columns[$val] = null;
					unset($columns[$key]);
				}
			}
		} else {
			$columns = [$columns => null];
		}
		
		return new AnonFieldHandler(
			SC::S_QUERY|SC::C_SELECT,
			[$field],
			function (SC $sqlContext, $fieldsIn, & $fieldsOut) use ($field, $columns) {
				foreach ($columns as $key => $val) {
					$fieldsOut[$key] = $val;
				}
			},
			function ($selectedFields, $rowsIn, & $rowsOut) use ($field, $deriveFromRow) {
				foreach ($rowsIn as $i => $rowIn) {
					$rowsOut[$i][$field] = $deriveFromRow($rowIn);
				}
			}
		);
	}
	
	public static function simpleField($field, $column = null, \Closure $encodeValue = null, \Closure $decodeValue = null) {
		// TODO: Optimize?
		if ($column === null) {
			$column = 'this.' . $field;
			$resultColumn = $field;
		} else {
			$pos = strpos($column, '.');
			$resultColumn = $pos === false ? $column : substr($column, $pos + 1);
		}
		
		return new AnonFieldHandler(
			SC::FULL_MASK,
			[$field],
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($field, $column, $encodeValue) {
				if ($sqlContext->getMask() & (SC::S_QUERY | SC::C_SELECT)) {
					$columnsOut[$column] = null;
				} else {				
					if ($encodeValue) {
						$v = $fieldsIn[$field];
						$columnsOut[$column] = $v instanceof Expr ? $v->transformed($encodeValue) : $encodeValue($v);
					} else {
						$columnsOut[$column] = $fieldsIn[$field];
					}
				}
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($field, $resultColumn, $decodeValue) {
				if ($decodeValue) {
					foreach ($rowsIn as $i => $rowIn) {
						$recordsOut[$i][$field] = $decodeValue($rowIn[$resultColumn]);
					}
				} else {
					foreach ($rowsIn as $i => $rowIn) {
						$recordsOut[$i][$field] = $rowIn[$resultColumn];
					}
				}
			}
		);
	}
}