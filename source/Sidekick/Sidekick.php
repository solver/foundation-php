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
 
use Solver\Sql\SqlConnection;
use Solver\SqlX\SqlUtils;

// TODO: Consider a function like EntitySet's combined SELECT FOR UPDATE + UPDATE combo:  mapByPK($pk, ($currentEntityState) => $newEntityState).
// queryAndUpdate('Foo')->select()->where()->execute($mapper);
class Sidekick {
	protected $schema;
	
	/**
	 * @var \Solver\Sql\SqlConnection
	 */
	protected $conn;
	
	public function __construct(SqlConnection $conn, Schema $schema) {
		$this->conn = $conn;
		$this->schema = $schema->render();
	}

	function insert($tableName, ...$rows) {
		$tableSchema = $this->getTableSchema($tableName);
		
		// TODO: Support creating linked rows (inferred from colNamespace) i.e. deep create. We have to consider how to
		// support also deep update and deep delete before that, for consistency.
		// TODO: Validate cols given.
		$rows = $this->mapRows(true, $tableSchema, $rows);
		SqlUtils::insertMany($conn, $tableSchema['internalName'], $rows, true);
	}
	
	function update($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		
		return new UpdateStatement(function ($stmt) use ($tableSchema) {
			$conn = $this->conn;
			
			if ($stmt['whereFields']) {
				$whereSql = $this->getWhereClause($tableSchema, $stmt['whereFields']);
			} else {
				$whereSql = '1 = 1';
			}
			
			$setSql = [];
			$setFields = $this->mapRows(true, $tableSchema, [$stmt['setFields']])[0];
			foreach ($setFields as $k => $v) {
				if (!is_scalar($v)) throw new \Exception('The values for fields in a set clause must be scalar values, or encode to scalar values.');
				$setSql[] = $conn->encodeIdent($k) . ' = ' . $conn->encodeValue($v);
			}
			
			$setSql = implode(', ', $setSql);
			$sql = 'UPDATE ' . $conn->encodeIdent($tableSchema['internalName']) . ' SET ' . $setSql . ' WHERE ' . $whereSql;
			return $conn->execute($sql);
		});
	}
	
	function updateByPK($tableName, $row) {
		$tableSchema = $this->getTableSchema($tableName);
		list($where, $set) = $this->splitRowByPK($tableSchema, $row);
		return $this->update($tableName)->set($set)->where($where)->execute();
	}
	
	function query($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		
		return new QueryStatement(function ($stmt, $getAll, $column) use ($tableSchema) {
			$resultset = $this->queryInternal($tableSchema, $stmt);
			
			if ($getAll) {
				$rows = $resultset->getAll($column);
				
				return $this->mapRows(false, $tableSchema, $rows);
			} else {
				$row = $resultset->getOne($column);
				
				if ($row === null) {
					return null;
				} else {
					return $this->mapRows(false, $tableSchema, [$row])[0];
				}
			}
		});
	}
	
	function queryAndUpdateByPK($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		
		// TODO: Wrap in a nested transaction here when SqlConnection support them (again).
		return new QueryAndUpdateByPKStatement(function ($stmt, $mapper) use ($tableName, $tableSchema) {
			// We'll be updating so we force this without making the user do it manually.
			$stmt['forShare'] = false;
			$stmt['forUpdate'] = true;
			
			// TODO: The get/update loop should stream results for a large number of results, to avoid going out of RAM.
			$rows = $this->queryInternal($tableSchema, $stmt)->getAll();
			
			foreach ($resultset as $row) {
				$this->updateByPK($tableName, $mapper($row));
			}
			
			return count($resultset);
		});
	}
		
	function delete($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
			
		return new DeleteStatement(function ($stmt) use ($tableSchema) {
			$conn = $this->conn;
			
			$whereClause = [];
			
			if ($smt['whereFields']) {
				$whereSql = $this->getWhereClause($tableSchema, $stmt['whereFields']);
			} else {
				// To avoid deleting full tables by accident. Might remove this.
				throw new \Exception('Delete statements must have a defined "where" clause.');
			}
			
			$sql = 'DELETE FROM ' . $this->conn->quoteIdent($tableSchema['internalName']) . ' WHERE ' . $whereSql;
			return $conn->execute($sql);
		});
	}
	
	protected function queryInternal($tableSchema, $statement) {
		$conn = $this->conn;

		$sql = 'SELECT ';
		if ($stmt['distinct']) $sql .= 'DISTINCT ';
		if ($stmt['distinctRow']) $sql .= 'DISTINCTROW ';
		
		if ($stmt['selectFields']) {
			// TODO: Support expressions.
			$selects = [];
			$selectFields = $this->mapFieldNames(true, $tableSchema, $stmt['selectFields']);
			foreach ($selectFields as $k => $v) {
				$selects[] = $conn->encodeIdent($k);
			}
			$sql .= implode(', ', $selects) . ' ';
		} else {
			$sql .= '* ';
		}
		
		$sql .= 'FROM ' . $conn->encodeIdent($tableSchema['internalName']) . ' ';
		
		if ($stmt['whereFields']) {
			$sql .= 'WHERE ' . $this->getWhereClause($tableSchema, $stmt['whereFields']) . ' ';
		}
		
		if ($stmt['orderFields']) {
			$sql .= 'ORDER BY ' . SqlUtils::orderBy($conn, $stmt['orderFields']);
		}
		
		if ($stmt['limit']) {
			$sql .= 'LIMIT ' . $conn->encodeValue($stmt['limit']) . ' ';
			if ($stmt['offset']) {
				$sql .= 'OFFSET ' . $conn->encodeValue($stmt['offset']) . ' ';
			}
		}
		
		if ($stmt['forShare']) {
			$sql .= 'LOCK IN SHARE MODE ';
		}
		
		if ($stmt['forUpdate']) {
			$sql .= 'FOR UPDATE ';
		}
		
		return $conn->query($sql);
	}
	protected function splitRowByPK($tableSchema, $row) {
		if ($tableSchema['primaryKey'] === null) throw new \Exception('The operation cannot be performed as table ' . $tableSchema['name'] . ' has no defined primary key column(s).');
		// TODO: Support composite PK.
		if (count($tableSchema['primaryKey'] === null) > 1) throw new \Exception('No support for composite PK yet.');
		
		$pkSchema = $tableSchema['primaryKey'];
		$pkName = $pkSchema[0];
		
		if (isset($row[$pkName]) || key_exists($pkName, $row)) {
			$pkValue = $row[$pkName];
			unset($row[$pkName]);
			return [[$pkName => $pkValue], $row];
		}
	}
	
	protected function getWhereClause($tableSchema, $fields) {
		$conn = $this->conn;
		$whereClause = [];
		
		$fields = $this->mapRows(true, $tableSchema, [$fields])[0];
		
		foreach ($fields as $k => $v) {
			if ($v instanceof Expr) {
				$whereClause[] = $v->render($conn, $k);
			} else { 
				$whereClause[] = SqlUtils::boolean($conn, [$k => $v]);
			}
		}
		
		return implode(' AND ', $whereClause);
	}
	
	protected function getWhereExprFromPK($tableSchema, ...$pkList) {
		// static $badPK = 'Supplied primary key doesn\'t match the table composite primary key schema.';
		
		if ($tableSchema['primaryKey'] === null) throw new \Exception('The operation cannot be performed as table ' . $tableSchema['name'] . ' has no defined primary key column(s).');
		// TODO: Support composite PK.
		if (count($tableSchema['primaryKey'] === null) > 1) throw new \Exception('No support for composite PK yet.');
		
		$pkSchema = $tableSchema['primaryKey'];
		
// 		if (isset($pkSchema[1])) {
// 			$pkClean = [];
			
// 			foreach ($pkSchema as $col) {
// 				if (isset($pk[$col])) {
// 					$pkClean[$col] = $pk[$col];
// 					unset($pk[$col]);
// 				} else {
// 					throw new \Exception($badPK);
// 				}
// 			}	
			
// 			if ($pk) throw new \Exception($badPK);
			
// 			return $selector->where($pkClean);
// 		} else { ... 
		if (count($pkList) === 1) {
			return [$pkSchema[0] => $pkList[0]];
		} else {
			return [$pkSchema[0] => new InExpr($pkList)];
		}
//		... }
	}
	
	protected function getTableSchema($tableName) {
		if (!isset($this->schema['tables'][$tableName])) throw new \Exception('Table name ' . $tableName . ' is not defined in the given schema.');
		return $this->schema['tables'][$tableName];
	}
	
	protected function mapFieldNames($encoding, $tableSchema, $fieldsIn) {
		$fieldsOut = [];
		
		$colSchemaList = $encoding ? $tableSchema['externalFieldList'] : $tableSchema['internalFieldList'];
		$colSchemaIndex = $encoding ? $tableSchema['externalFields'] : $tableSchema['internalFields'];
				
		// TODO: Block fields with params which shouldn't have any (or has but are wrong), like column fields.
		// TODO: Bench if a simple array_flip(array_keys())+isset, or similar, is faster than multiple key_exists().
		foreach ($colSchemaList as list($isComposite, $colOrColGroup)) {
			if ($isComposite) {
				$thisColSchema = $colSchemaIndex[$colOrColGroup[0]];
			} else {
				$thisColSchema = $colSchemaIndex[$colOrColGroup];
			}
			
			if ($isComposite) {				
				$hasAll = true;
				$hasSome = false;
				
				foreach ($colOrColGroup as $col) {
					$processedGroupFields[$col] = true;
					
					if (key_exists($col, $fieldsIn)) {
						$hasSome = true;
						unset($fieldsIn[$col]);
					} else {
						$hasAll = false;
					}
				}
				
				if (!$hasSome) continue; 
				if (!$hasAll) throw new \Exception('All or none ' . ($encoding ? 'public' : 'internal') . ' columns in group "' . implode('", "', $colOrColGroup) . '" should be specified in a select. Some, but not all were specified.');
			
				// TODO: We ignore values... Columns have no values but that should be revised for the other types.
				foreach ($thisColSchema['toName'] as $name) {
					$fieldsOut[$name] = null;
				}
			} else {
				if (!key_exists($colOrColGroup, $fieldsIn)) continue;
				unset($fieldsIn[$colOrColGroup]);
				
				// TODO: We ignore values... Columns have no values but that should be revised for the other types.
				$fieldsOut[$thisColSchema['toName']] = null;
			}
		}
		
		if ($fieldsIn) {
			throw new \Exception('Undeclared fields: "' . implode('", "', array_keys($fieldsIn)) . '".');	
		}
		
		return $fieldsOut;
	}
	
	protected function mapRows($encoding, $tableSchema, $inputRows) {
		// Transposed.
		$transRowsIn = [];
		$transRowsOut = [];
		
		foreach ($inputRows as $rowIndex => $row) {
			foreach ($row as $colIndex => $value) {
				$transRowsIn[$colIndex][$rowIndex] = $value;
			}
		}
				
		$colSchemaList = $encoding ? $tableSchema['externalFieldList'] : $tableSchema['internalFieldList'];
		$colSchemaIndex = $encoding ? $tableSchema['externalFields'] : $tableSchema['internalFields'];
				
		// TODO: Switch to iterating the given row keys, not all keys (will be faster).
		foreach ($colSchemaList as list($isComposite, $colOrColGroup)) {
			if ($isComposite) {
				$thisColSchema = $colSchemaIndex[$colOrColGroup[0]];
			} else {
				$thisColSchema = $colSchemaIndex[$colOrColGroup];
			}
			
			/* @var $transform \Closure */
			$transform = $thisColSchema['transform'];
			
			// Handles Expr instances, which happens only while encoding (we avoid it on decoding as it's a performance hit).
			if ($encoding) $transform = $this->getTransformWithExprSupport($transform);
			
			if (!$transform) {
				// Note that groups always have a transform, so we're sure this one is not a group.
				$transRowsOut[$colOrColGroup] = $transRowsIn[$colOrColGroup];
			} else {
				if ($isComposite) {					
					$hasAll = true;
					$hasSome = false;
					$transformInput = [];
					foreach ($colOrColGroup as $col) {						
						if (isset($transRowsIn[$col])) {
							$hasSome = true;
							$transformInput[$col] = $transRowsIn[$col];
							unset($transRowsIn[$col]);
						} else {
							$hasAll = false;
						}
					}
					
					if (!$hasSome) continue;
					if (!$hasAll) throw new \Exception('All or none ' . ($encoding ? 'public' : 'internal') . ' columns in group "' . implode('", "', $colOrColGroup) . '" should be specified. Some, but not all were specified: "' . implode('", "', array_keys($transformInput)) . '".');

					$transformInput = $transform($transformInput, true);
					
					// TODO: We need to validate the transform output if it matches the expected format (a list, or a map of lists with specific keys)
					// Or this here produces odd errors when the encode/decode filter is wrong. Maybe assertions.
					$transRowsOut += $transformInput;
				} else {
					if (!isset($transRowsIn[$colOrColGroup])) continue;
					
					$transformInput = $transform($transRowsIn[$colOrColGroup], false);
					unset($transRowsIn[$colOrColGroup]);
					
					// TODO: We need to validate the transform output if it matches the expected format (a list, or a map of lists with specific keys)
					// Or this here produces odd errors when the encode/decode filter is wrong. Maybe assertions.
					$transRowsOut[$thisColSchema['toName']] = $transformInput;
				}
			}
		}

		if ($transRowsIn) {
			throw new \Exception('Undeclared fields: "' . implode('", "', array_keys($transRowsIn)) . '".');	
		}
		
		$outputRows = [];
		
		foreach ($transRowsOut as $rowIndex => $row) {
			foreach ($row as $colIndex => $value) {
				$outputRows[$colIndex][$rowIndex] = $value;
			}
		}
		
		return $outputRows;
	}
	
	protected function getTransformWithExprSupport($transform) {
		return function ($in, $composite) use ($transform) {
			if (!$in) return $in;
			
			if ($composite) {
				// We pass through here, composite encoders are required to deal with Expr on their own (or throw).
				return $transform($in, true);
			} else {
				// We hide Expr instances from simple transforms, so they don't have to know about Expr at all.
				// TODO: Detect lack of Expr instance, pass-through in that case.
				$out = [];
				foreach ($in as $val) {
					if ($val instanceof Expr) {
						$val = $val->getTransformed($transform);
					} else {
						$valList = $transform([$val], false);
						$val = $valList[0];
					}
					
					$out[] = $val;
				}
			}
			
			return $out;
		};
	}
}