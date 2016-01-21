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

// FIXME: Add "hasOne" and "hasMany" composite fields that run sub-query (a distinct query).
// SELECT for complex field by default, and allow users to expand from there (choose carefully what we allow by default).

/**
 * TODO: Remove underscore suffix from methods once we drop PHP 5.x support.
 * 
 * TODO: Optimization opportunities for composite/alternate/joint doecs (avoid selecting fields second time after
 * Sidekick does it). Might require extra integration work with Sidekick.
 */
class BasicFields {
	public static function json($field, $column = null, $mask = null) {
		return self::simple($field, $column, $mask,
			function ($value) { return json_encode($value); },
			function ($value) { return json_decode($value, true); }
		);
	}
	
	public static function bool($field, $column = null, $mask = null) {
		return self::simple($field, $column, $mask,
			function ($value) { return $value ? 1 : 0; },
			function ($value) { return (bool) $value; }
		);
	}
	
	public static function string($field, $column = null, $mask = null) {
		$transform = function ($value) { return (string) $value; };
		return self::simple($field, $column, $mask, $transform, $transform);
	}
	
	public static function int($field, $column = null, $mask = null) {
		$transform = function ($value) { return (int) $value; };
		return self::simple($field, $column, $mask, $transform, $transform);
	}
	
	public static function float($field, $column = null, $mask = null) {
		$transform = function ($value) { return (float) $value; };
		return self::simple($field, $column, $mask, $transform, $transform);
	}
	
	public static function number($field, $column = null, $mask = null) {
		$transform = function ($value) { return +$value; };
		return self::simple($field, $column, $mask, $transform, $transform);
	}
	
	/**
	 * Timestamp integer on PHP's side, DATETIME string on SQL's side.
	 * 
	 * TODO: This probably doesn't belong here, or at least the standard one should produce PHP \DateTime(Immutable).
	 * 
	 * @return Codec
	 */
	public static function timestampFromDateTime($field, $column = null, $mask = null) {
		return self::simple($field, $column, $mask,
			function ($value) { return SqlUtils::toDatetime($value); },
			function ($value) { return SqlUtils::fromDatetime($value); }
		);
	}
	
	/**
	 * Composite field combines other field codecs to produce a single field (as a PHP array: dict/tuple/list).
	 * 
	 * @param string $field
	 * Field name for the composite value.
	 * 
	 * @param Codec ...$codecs
	 * One or more field codecs as sub-fields in the composite fields.
	 * 
	 * @return Codec
	 */
	public static function composite($field, Codec ...$codecs) {
		$combinedMask = SC::C_ALL; 
		$fieldMap = self::getFieldMap($codecs, $combinedMask, 'alternate codec');
		
		return new AnonCodec(
			$combinedMask,
			[$field],
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($field, $codecs, $fieldMap) {
				$subFieldsIn = $fieldsIn[$field];
				
				// TODO: We do this normalization all over the Sidekick codebase (and worse: with minor variations), we
				// need to unify it and extract it in CodecUtils.
				foreach ($subFieldsIn as $key => $val) {
					if (is_int($key)) {
						unset($subFieldsIn[$key]);
						$subFieldsIn[$val] = null;
					}
				}
				
				foreach (self::getCodecSet($subFieldsIn, $fieldMap) as $codecIndex => $nothing) {
					$codecs[$codecIndex]->encodeClause($sqlContext, $subFieldsIn, $columnsOut);
				}
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($field, $codecs, $fieldMap) {
				$subSelectedFields = $selectedFields[$field];
				$subRecordsOut = [];
				
				// TODO: We do this normalization all over the Sidekick codebase (and worse: with minor variations), we
				// need to unify it and extract it in CodecUtils.
				// TODO: We do this on both encode and decode... any way to cache (we should avoid ambiguous shared
				// state, there is no guarantee things will run in perfect order). If we had "pass by ref" fieldsIn that
				// would be solved.
				foreach ($subSelectedFields as $key => $val) {
					if (is_int($key)) {
						unset($subSelectedFields[$key]);
						$subSelectedFields[$val] = null;
					}
				}
				
				foreach (self::getCodecSet($subSelectedFields, $fieldMap) as $codecIndex => $nothing) {
					$codecs[$codecIndex]->decodeRows($subSelectedFields, $rowsIn, $subRecordsOut);
				}
				
				foreach ($subRecordsOut as $i => $v) {
					$recordsOut[$i][$field] = $v;
				}
			}
		);
	}
	
	/**
	 * It's a common practice to offer multiple alternative mappings of the same data interpreted as different fields.
	 * 
	 * While this is not a problem when you pass fields in read context (SELECT, WHERE, ORDER) it causes a conflict
	 * in write context (INSERT ... VALUES and UPDATE ... SET), so through this constraint you can ensure you don't 
	 * have conflicting fields in such contexts.
	 * 
	 * @param int|null|Codec $maskOrCodec
	 * A mask for which the constraint applies. If null, assumes SC::C_ALL_STATEMENTS | SC::C_VALUES | SC::C_SET.
	 * 
	 * If you don't want to customize the mask, you can pass a codec as a first argument. Those calls are equivalent:
	 * 
	 * - BasicFields::joint(null, $codecA, $codecB)
	 * - BasicFields::joint($codecA, $codecB)
	 * 
	 * @param Codec ...$codecs
	 * 
	 * @return Codec
	 */
	public static function alternate($maskOrCodec, Codec ...$codecs) {
		if ($maskOrCodec instanceof Codec) {
			$codecs[] = $maskOrCodec;
			$constraintMask = null;
		} else {
			$constraintMask = $maskOrCodec;
		}
		
		$constraintMask = $constraintMask ?: (SC::C_ALL_STATEMENTS | SC::C_VALUES | SC::C_SET); // TODO: Replace with ?? in PHP 7
		$combinedMask = 0;
		$fieldMap = self::getFieldMap($codecs, $combinedMask, 'alternate codec');
		
		return new AnonCodec(
			$combinedMask,
			array_keys($fieldMap),
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($fieldMap, $codecs, $constraintMask) {
				$mask = $sqlContext->getMask();
				
				if (($mask & $constraintMask) === $mask) {
					$selectedCodecIndex = null;
					$selectedField = null;
					
					foreach ($fieldMap as $field => $codecIndex) {
						if (isset($fieldsIn[$field]) || key_exists($field, $fieldsIn)) {
							if ($selectedCodecIndex === null) {
								$selectedCodecIndex = $codecIndex;
								$selectedField = $field;
							} elseif ($selectedCodecIndex !== $codecIndex) {
								throw new \Exception('Alternate fields "' . $selectedField . '" and "' . $field . '" must not be specified at the same time in ' . CodecUtils::contextMaskToString($mask) . '.');
							}
						}
					}
					
					$codecs[$selectedCodecIndex]->encodeClause($sqlContext, $fieldsIn, $columnsOut);
				} else {
					foreach (self::getCodecSet($fieldsIn, $fieldMap) as $codecIndex => $nothing) {
						$codecs[$codecIndex]->encodeClause($sqlContext, $fieldsIn, $columnsOut);
					}
				}
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($fieldMap, $codecs) {
				foreach (self::getCodecSet($selectedFields, $fieldMap) as $codecIndex => $nothing) {
					$codecs[$codecIndex]->decodeRows($selectedFields, $rowsIn, $recordsOut);
				}
			}
		);
	}
	
	/**
	 * Ensures either all of the fields defined by the sub-codecs are present, or none (partial set of fields not
	 * allowed).
	 * 
	 * @param int|null|Codec $maskOrCodec
	 * A mask for which the constraint applies. If null, uses a default of SC::C_ALL.
	 * 
	 * If you don't want to customize the mask, you can pass a codec as a first argument. Those calls are equivalent:
	 * 
	 * - BasicFields::joint(null, $codecA, $codecB)
	 * - BasicFields::joint($codecA, $codecB)
	 * 
	 * @param Codec ...$codecs
	 * 
	 * @return Codec
	 */
	public static function joint($maskOrCodec, Codec ...$codecs) {
		if ($maskOrCodec instanceof Codec) {
			$codecs[] = $maskOrCodec;
			$constraintMask = null;
		} else {
			$constraintMask = $maskOrCodec;
		}
		
		$constraintMask = $constraintMask ?: SC::C_ALL; // TODO: Replace with ?? in PHP 7
		$combinedMask = 0;
		$fieldMap = self::getFieldMap($codecs, $combinedMask, 'joint codec');
		
		return new AnonCodec(
			$combinedMask,
			array_keys($fieldMap),
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($fieldMap, $codecs, $constraintMask) {
				$mask = $sqlContext->getMask();
				
				if (($mask & $constraintMask) === $mask) {
					$count = 0;
					foreach ($fieldMap as $fieldName => $nothing) {
						if (isset($fieldsIn[$fieldName]) || key_exists($fieldName, $fieldsIn)) {
							$count++;
						}
					}
					
					if ($count != 0 && $count != count($fieldMap)) {
						// TODO: Wording.
						throw new \Exception('All or none of joint fields "' . implode('", "', array_keys($fieldMap)) . '" should be specified in ' . CodecUtils::contextMaskToString($mask) . '.');
					}
				}
				
				foreach ($codecs as $codecIndex => $nothing) {
					$codecs[$codecIndex]->encodeClause($sqlContext, $fieldsIn, $columnsOut);
				}
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($codecs) {
				foreach ($codecs as $codec) {
					$codec->decodeRows($selectedFields, $rowsIn, $recordsOut);
				}
			}
		);
	}
	
	// Produces a select-only field from a set of selected columns and expressions, and can locally compute a derived
	// value from them (or any other data on the result set row).
	// TODO: Combine with exprField()?
	// TODO: Document that it's readonly and $columns supports three formats in same array (tbl is optional everywhere):
	// - Null if the field doesn't need to add columns over what is selected by the caller.
	// - Basic string is column, read as is (already supported)
	// - List of strings is multiple columns.
	// - Dict of ["tbl.foo" => null] is same as list like ["tbl.foo"], latter is just shorthand.
	// - Dict of ["foo" => "tbl.bar"] is column "bar" read as alias "foo" (bar AS foo)
	// - Dict of ["foo" => new Expr()] is SQL expression read as alias "foo".
	public static function virtual($field, $columns, $decodeValueFromRow) {
		// Normalize.
		if ($columns !== null) {
			if (is_array($columns)) {
				foreach ($columns as $key => $val) {
					if (is_int($key)) {
						$columns[$val] = null;
						unset($columns[$key]);
					}
				}
			} elseif ($columns instanceof Expr) {
				$columns = [$field => $columns];
			} else {
				$columns = [$columns => null];
			}
		}
		
		return new AnonCodec(
			SC::C_QUERY_STATEMENT | SC::C_SELECT,
			[$field],
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($field, $columns) {
				$columnsOut += $columns;
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($field, $decodeValueFromRow) {
				foreach ($rowsIn as $i => $rowIn) {
					$recordsOut[$i][$field] = $decodeValueFromRow($rowIn);
				}
			}
		);
	}
	
	// Produces a constant value or expression as a column named like the given field. User field values are disallowed
	// (they can just specify the field is present or not in a given context).
	public static function const_($field, $const, $mask = null, \Closure $decodeValue = null) {
		return new AnonCodec(
			$mask === null ? SC::C_ALL : $mask,
			[$field],
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($field, $const) {
				if ($fieldsIn[$field] !== null) throw new \Exception('Field "' . $field . '" doesn\'t support custom values.');
				$columnsOut[$field] = $const;
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($field, $decodeValue) {
				if ($decodeValue) {
					foreach ($rowsIn as $i => $rowIn) {
						$recordsOut[$i][$field] = $decodeValue($rowIn[$field]);
					}
				} else {
					foreach ($rowsIn as $i => $rowIn) {
						$recordsOut[$i][$field] = $rowIn[$field];
					}
				}
			}
		);
	}
	
	// $column can be null (use this.field as col name); string, expr, or array with one single item [alias => colname|expr].
	// TODO: Add $generateValue closure that runs always on insert. Requires extending the Codec interface, say a mask
	// to always trigger the handler, even if the field for the handler is not there.
	public static function simple($field, $column = null, $mask = null, \Closure $encodeValue = null, \Closure $decodeValue = null) {
		// TODO: Simplify, optimize, move to utils for reuse?
		if ($column === null) {
			$column = 'this.' . $field;
			$alias = $field;
		} elseif ($column instanceof Expr) {
			$column = [$field => $column];
			$alias = $field;
		} elseif (is_array($column)) {
			if (count($column) != 1) throw new \Exception('Simple fields require exactly one column in an array $column.');
			
			$val = reset($column);
			$key = key($column);
			
			if ($key === null) {
				$column = $key;
				
				$pos = strpos($key, '.');
				$alias = $pos === false ? $key : substr($key, $pos + 1);
			} else {
				$column = $val;
				$alias = $key;
			}
		} else {
			$pos = strpos($column, '.');
			$alias = $pos === false ? $column : substr($column, $pos + 1);
		}
		
		return new AnonCodec(
			$mask === null ? SC::C_ALL : $mask,
			[$field],
			function (SC $sqlContext, $fieldsIn, & $columnsOut) use ($field, $column, $alias, $encodeValue) {
				$mask = $sqlContext->getMask();
				
				if ($mask & SC::C_SELECT) {
					if ($fieldsIn[$field] !== null) throw new \Exception('Field "' . $field . '" doesn\'t support custom values in a SELECT clause.');
					$columnsOut[$alias] = $column;
				} else {	
					if ($encodeValue) {
						$v = $fieldsIn[$field];
						$columnsOut[$column] = $v instanceof Expr ? $v->transformed($encodeValue) : $encodeValue($v);
					} else {
						$columnsOut[$column] = $fieldsIn[$field];
					}
				}
			},
			function ($selectedFields, $rowsIn, & $recordsOut) use ($field, $alias, $decodeValue) {
				if ($decodeValue) {
					foreach ($rowsIn as $i => $rowIn) {
						$recordsOut[$i][$field] = $decodeValue($rowIn[$alias]);
					}
				} else {
					foreach ($rowsIn as $i => $rowIn) {
						$recordsOut[$i][$field] = $rowIn[$alias];
					}
				}
			}
		);
	}

	protected static function getFieldMap($codecs, & $combinedMask, $parentLabel) {
		$fieldMap = [];
		
		foreach ($codecs as $codecIndex => $codec) {
			$combinedMask |= $codec->getMask();
			
			foreach ($codec->getFields() as $field) {
				if (isset($fieldMap[$field])) {
					throw new \Exception('Duplicate declaration of field "' . $field . '" for ' . $parentLabel . ' at sub-codec index ' . $fieldMap[$field] . ' and ' . $codecIndex . '.');
				}
				
				$fieldMap[$field] = $codecIndex;
			}
		}
		
		return $fieldMap;
	}
	
	protected static function getCodecSet($fieldsIn, $fieldMap) {
		$codecSet = [];
				
		foreach ($fieldMap as $field => $codecIndex) {
			if (isset($fieldsIn[$field]) || key_exists($field, $fieldsIn)) {
				$codecSet[$codecIndex] = true;
			}
		}
		
		return $codecSet;
	}
}