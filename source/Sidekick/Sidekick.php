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
 
use Solver\Sidekick\SqlContext;
use Solver\Sql\SqlSession;
use Solver\Sidekick\SqlContext as SC;

// TODO: The internal mapping methods (map*() are currently factored for granularity and understandability, not
// performance, this should tilt towards "performance" when the logic and features are stable).
class Sidekick {
	protected $schema;
	
	/**
	 * @var \Solver\Sql\SqlSession
	 */
	protected $sqlSess;
	
	public function __construct(SqlSession $sqlSess, Schema $schema) {
		$this->sqlSess = $sqlSess;
		$this->schema = $schema->render();		
	}

	// IMPORTANT: We don't return generated id if you pass multiple rows to insert at once, only when you pass one row.
	// There are differences in behavior across databases that need to be ironed out, and Sidekick needs work to support
	// this reliably as well. It seems MySQL returns first id on extended insert, and rest are consecutive:
	// https://dev.mysql.com/doc/refman/5.6/en/innodb-auto-increment-configurable.html <-- research depends on lock
	// settings.
	function insert($tableName, ...$rows) {
		$tableSchema = $this->getTableSchema($tableName);
		$sess = $this->sqlSess;
		
		// TODO: Support creating linked rows (inferred from colNamespace) i.e. deep create. We have to consider how to
		// support also deep update and deep delete before that, for consistency.
		// TODO: Support returning multiple insert ids, not just last one. Might need to differentiate insert() from
		// insertMany(). For now people need to call insert() many times if they want all ids.
		foreach ($rows as $row) {
			$sql = $this->renderInsertStatement($sess, $tableSchema, ['valuesFields' => $row]);
			$sess->execute($sql);
		}
		
		// Note we only return generated id if one passes a single row. This is intentional. Other cases need work.
		// TODO: Support PgSQL SERIAL type and generated sequences (latter will need extra support in Table::setPK to
		// specify the sequence name).
		if (count($rows) == 1 && $tableSchema['pkFieldIsGenerated']) {
			return $this->decodeGeneratedPK($sess, $tableSchema, $sess->getLastInsertId());
		}
	}
	
	function update($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		$sess = $this->sqlSess;
		
		return new UpdateStatement(function ($stmt) use ($tableSchema, $sess) {
			$sql = $this->renderUpdateStatement($sess, $tableSchema, $stmt);
			return $sess->execute($sql);
		});
	}
	
	function updateByPK($tableName, $row) {
		$tableSchema = $this->getTableSchema($tableName);
		list($where, $set) = $this->splitRowByPK($tableSchema, $row);
		return $this->update($tableName)->set($set)->where($where)->execute();
	}
	
	function query($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		$sess = $this->sqlSess;
		
		return new QueryStatement(function ($stmt, $getAll, $column) use ($tableSchema, $sess) {
			$sql = $this->renderQueryStatement($sess, $tableSchema, $stmt);
			$resultSet = $sess->query($sql);
			
			if ($getAll) {
				$rows = $resultSet->getAll($column);
				$rows = $this->decodeRows($tableSchema, $stmt['selectFields'], $rows);
				
				return $rows;
			} else {
				$row = $resultSet->getOne($column);
				if ($row !== null) {
					$row = $this->decodeRows($tableSchema, $stmt['selectFields'], [$row])[0];
				}
				
				return $row;
			}
		});
	}
	
	function queryAndUpdateByPK($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		$sess = $this->sqlSess;
		
		// TODO: Wrap in a nested transaction here when SqlSession support them (again).
		return new QueryAndUpdateByPKStatement(function ($stmt, $mapper) use ($tableName, $tableSchema, $sess) {
			// We run this in a real/virtual transaction, so we commit & fail atomically.
			$tid = $sess->begin(null, $sess::TF_VIRTUAL);
			
			try {
				// We'll be updating so we force this without making the user do it manually.
				$stmt['forShare'] = false;
				$stmt['forUpdate'] = true;
				
				$sql = $this->renderQueryStatement($sess, $tableSchema, $stmt);
				
				// TODO: The get/update loop should stream results for a large number of results, to avoid going out of RAM.
				$rows = $sess->query($sql)->getAll();
				$rows = $this->decodeRows($tableSchema, $stmt['selectFields'], $rows);
				
				foreach ($rows as $row) {
					$this->updateByPK($tableName, $mapper($row));
				}
				
				$sess->commit($tid);
				return count($rows);
			} catch (\Exception $e) {
				$sess->rollback($tid);
				throw $e;
			}
		});
	}
		
	function delete($tableName) {
		$tableSchema = $this->getTableSchema($tableName);
		$sess = $this->sqlSess;
			
		return new DeleteStatement(function ($stmt) use ($tableSchema, $sess) {
			$sql = $this->renderDeleteStatement($sess, $tableSchema, $stmt);
			return $sess->execute($sql);
		});
	}
	
	protected function renderInsertStatement(SqlSession $sess, $tableSchema, $stmt) {
		/*
		 * Render clauses.
		 */
		
		$mask = SC::S_INSERT;
		$allowJoins = false;
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		// TODO: This should never show up as we don't have an InsertStatement object (yet). Fix the error message if/when we do.
		if (!$stmt['valuesFields']) throw new \Exception('Please specify the fields to assign via a "values" clause.');
		
		$valuesClause = $this->renderValues($sqlContext, $mask, $tableSchema, $stmt['valuesFields']);
		
		/*
		 * Build statement.
		 */
		
		$sql = 'INSERT INTO ' . $sess->encodeIdent($tableSchema['internalName']) . ' ' . $valuesClause;
		return $sql;
	}
	
	protected function renderQueryStatement(SqlSession $sess, $tableSchema, $stmt) {
		/*
		 * Render clauses.
		 */
		
		$mask = SC::S_QUERY;
		$allowJoins = true;
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		if (!$stmt['selectFields']) throw new \Exception('Please specify the fields to read via select().');
		
		$selectClause = $this->renderSelect($sqlContext, $mask, $tableSchema, $stmt['selectFields']);
		$whereClause = $stmt['whereFields'] ? $this->renderWhere($sqlContext, $mask, $tableSchema, $stmt['whereFields']) : null;
		$orderClause = $stmt['orderFields'] ? $this->renderOrder($sqlContext, $mask, $tableSchema, $stmt['orderFields']) : null;
		$joinClause = $usedJoins ? $this->renderJoin($sqlContext, $mask, $tableSchema, $usedJoins) : null;
		
		/*
		 * Build statement.
		 */
		
		$sql = 'SELECT ';
		if ($stmt['distinct']) $sql .= 'DISTINCT ';
		if ($stmt['distinctRow']) $sql .= 'DISTINCTROW ';
		
		$sql .= $selectClause . ' ';
		
		$sql .= 'FROM ' . $sess->encodeIdent($tableSchema['internalName']) . ' this ';
		
		if ($joinClause !== null) $sql .= $joinClause . ' ';
		if ($whereClause !== null) $sql .= 'WHERE ' . $whereClause . ' ';
		if ($orderClause !== null) $sql .= 'ORDER BY ' . $orderClause . ' ';
		
		if ($stmt['limit']) {
			$sql .= 'LIMIT ' . $sess->encodeValue($stmt['limit']) . ' ';
			
			if ($stmt['offset']) {
				$sql .= 'OFFSET ' . $sess->encodeValue($stmt['offset']) . ' ';
			}
		}
		
		if ($stmt['forShare']) $sql .= 'LOCK IN SHARE MODE ';
		if ($stmt['forUpdate']) $sql .= 'FOR UPDATE ';
		
		return $sql;
		
	}
	
	protected function renderUpdateStatement(SqlSession $sess, $tableSchema, $stmt) {
		/*
		 * Render clauses.
		 */
		
		$mask = SC::S_UPDATE;
		$allowJoins = false; // TODO: Support join.
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		if (!$stmt['setFields']) throw new \Exception('Please specify the fields to update via set().');
		
		$setClause = $this->renderSet($sqlContext, $mask, $tableSchema, $stmt['setFields']);
		$whereClause = $stmt['whereFields'] ? $this->renderWhere($sqlContext, $mask, $tableSchema, $stmt['whereFields']) : null;
		
		/*
		 * Build statement.
		 */
		
		$sql = 'UPDATE ' . $sess->encodeIdent($tableSchema['internalName']) . ' this SET ' . $setClause;
		
		if ($whereClause !== null) $sql .= $whereClause . ' ';
		
		return $sql;
	}
	
	protected function renderDeleteStatement(SqlSession $sess, $tableSchema, $stmt) {
		/*
		 * Render clauses.
		 */
		
		$mask = SC::S_DELETE;
		$allowJoins = false; // TODO: Support join.
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		// To avoid deleting full tables by accident. Might remove this.
		if (!$stmt['whereFields']) throw new \Exception('Please specify the rows to delete via where().');
		
		$whereClause = $this->renderWhere($sqlContext, $mask, $tableSchema, $stmt['whereFields']);
		
		// TODO: Support joins? More specific error?
		if ($usedJoins) throw new \Exception('A requested field needs joins, and DELETE statement doesn\'t support joins.');
		
		/*
		 * Build statement.
		 */
		
		$tableEn = $sess->encodeIdent($tableSchema['internalName']);
		
		$sql = 'DELETE FROM ' . $tableEn . ' USING ' . $tableEn . ' this WHERE ' . $whereClause . ' ';
		
		return $sql;
	}
	
	protected function renderSelect(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_SELECT;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		$expr = [];
		
		foreach ($fields as $key => $val) {
			// If the value is null, we render the key as a straight column select:
			// SELECT `key`.
			if ($val === null) {
				$expr[] = $sqlContext->encodeIdent($key, true);
			} 
			
			// If the value is a string, we render as an alias:
			// SELECT `val` AS `key`.
			elseif (is_string($val)) {
				$expr[] = $sqlContext->encodeIdent($val, true) . ' AS ' . $sqlContext->encodeIdent($key);
			}
			
			// If the value is an expression, we render it and assign the result the key as an alias (subject is null):
			// SELECT expr AS `key`.
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, null) . ' AS ' . $sqlContext->encodeIdent($key);
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a SELECT clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(', ', $expr);
	}
	
	protected function renderWhere(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_WHERE;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		$expr = [];
		
		foreach ($fields as $key => $val) {
			// Scalars and null produce a basic ident-to-value equality comparison:
			// WHERE `key` = "val"
			if ($val === null || is_scalar($val)) {
				$expr[] = $sqlContext->encodeIdent($key, true) . ' = ' . $sqlContext->encodeValue($val);
			} 
			
			// Expressions are rendered with the key encoded as an identifier for subject.
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, $sqlContext->encodeIdent($key, true));
			} 
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a WHERE clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(' AND ', $expr);
	}
	
	protected function renderGroup(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_GROUP;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		
		$mask = $maskPrev;
		throw new \Exception('Pending implementation...');
	}
	
	protected function renderHaving(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_HAVING;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		
		$mask = $maskPrev;
		throw new \Exception('Pending implementation...');
	}
	
	protected function renderOrder(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_ORDER;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		$expr = [];
		
		foreach ($fields as $key => $val) {
			// If the value is null or string ASC, we render a simple order-by-ident expression (null is same as ASC):
			// ORDER BY `key` val.
			if ($val === null || $val === 'ASC') {
				$expr[] = $sqlContext->encodeIdent($key, true);
			} 
			
			// And similar for DESC:
			// ORDER BY `key` DESC.
			elseif ($val ==='DESC') {
				$expr[] = $sqlContext->encodeIdent($key, true) . ' DESC';
			}
			
			// If the value is an expression, we render it and give it the encoded key as subject:
			// SELECT expr AS `key`.
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, $sqlContext->encodeIdent($key, true));
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in an ORDER clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(', ', $expr);
	}
	
	// TODO: Extended insert?
	protected function renderValues(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_VALUES;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		$cols = [];
		$vals = [];
		
		foreach ($fields as $key => $val) {
			$cols[] = $sqlContext->encodeIdent($key);
				
			// If the value is a scalar or null it's rendered as a simple col-set-to-value (null becomes SQL NULL):
			// (`key`, ...) VALUES ("value", ...)
			if ($val === null || is_scalar($val)) {
				$vals[] = $sqlContext->encodeValue($val);
			} 
			
			// If the value is an expression, we render it and give it the encoded key as subject (typically ignored):
			// (`key`, ...) VALUES (expr, ...)
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, $sqlContext->encodeIdent($key));
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a VALUES clause.');
			}
		}
		
		$mask = $maskPrev;
		return '(' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
	}
	
	protected function renderSet(SqlContext $sqlContext, & $mask, $tableSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_SET;
		$fields = $this->encodeClause($sqlContext, $tableSchema, $fields);
		$expr = [];
		
		foreach ($fields as $key => $val) {
			// If the value is a scalar or null it's rendered as a simple col-set-to-value (null becomes SQL NULL):
			// SET `key` = "value"
			if ($val === null || is_scalar($val)) {
				$vals[] = $sqlContext->encodeIdent($key, true) . ' = ' . $sqlContext->encodeValue($val);
			} 
			
			// If the value is an expression, we render it as a col-set-to-expression and give it the encoded key as
			// subject (typically ignored):
			// SET `key` = expr
			elseif ($val instanceof Expr) {
				$expr[] = $sqlContext->encodeIdent($key, true) . ' = ' . $val->render($sqlContext, null);
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a SET clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(' ', $expr);
	}
	
	protected function renderJoin(SqlContext $sqlContext, & $mask, $tableSchema, $usedJoins) {
		$maskPrev = $mask; $mask |= SC::C_JOIN;
		$expr = [];
		$joins = $tableSchema['joins'];
		
		foreach ($usedJoins as $joinAlias => $nothing) {
			if (!isset($joins[$joinAlias])) throw new \Exception('The statement refers to non-existing join alias "' . $joinAlias . '".');
			
			list($tableName, $condition) = $joins[$joinAlias];
			
			$join = 'JOIN ' . $sqlContext->encodeIdent($tableName) . ' ' . $sqlContext->encodeIdent($joinAlias) . ' ON ';
			
			if ($condition instanceof Expr) {
				$join .= $condition->render($sqlContext, null);
			} else {
				$matches = [];
				
				// TODO: Support columns with table aliases here that can trigger a join (recursive join conditions).
				// For now we assume keys implicitly refer to the root table ("this") and the values refer to the newly
				// joined table.
				$thisEn = $sqlContext->encodeIdent('this');
				$thatEn = $sqlContext->encodeIdent($joinAlias);
				
				// Should we support [$key => new Expr()]? Probably. For now, we don't.
				foreach ($condition as $key => $val) {
					if (is_int($key)) {
						$valEn = $sqlContext->encodeIdent($val);
						$matches[] = $thisEn . '.' . $valEn . ' = ' . $thatEn . '.' . $valEn;
					} else {
						$matches[] = $thisEn . '.' . $sqlContext->encodeIdent($key) . ' = ' . $thatEn . '.' . $sqlContext->encodeIdent($val);
					}
				}
				
				$join .= implode(' ', $matches);
			}
			
			$expr[] = $join;
		}
		
		$mask = $maskPrev;
		return implode(' ', $expr);
	}
	
	protected function encodeClause(SqlContext $sqlContext, $tableSchema, $fieldsIn) {
		$handlers = $this->getHandlersFor($sqlContext->getMask(), $tableSchema, $fieldsIn);
		$fieldsOut = [];
		
		/* @var FieldHandler $handler */
		foreach ($handlers as $handler) {
			$handler->encodeClause($sqlContext, $fieldsIn, $fieldsOut); 
		}
		
		// TODO: We should allow this in some circumstances (say handlers which compute fields entirely "offline", or
		// use cache etc.), but for now we place this restriction that if fields are present, output should be as well,
		// because the render*() methods logic is not prepared to handle this situation, resulting in malformed queries.
		if (!$fieldsOut) {
			throw new \Exception('The field handlers produced no output for ' . InternalUtils::contextMaskToString($sqlContext->getMask()) . '.');
		}
		
		return $fieldsOut;
	}
	
	protected function decodeRows($tableSchema, $selectedFields, $rowsIn) {
		$handlers = $this->getHandlersFor(0, $tableSchema, $selectedFields);
		$rowsOut = [];
		
		/* @var FieldHandler $handler */
		foreach ($handlers as $handler) {
			$handler->decodeRows($selectedFields, $rowsIn, $rowsOut);
		}
		
		return $rowsOut;
	}
	
	protected function decodeGeneratedPK(SqlContext $sqlContext, $tableSchema, $generatedValue) {
		// This method performs a slightly awkward dance, simulating a user selecting the generated PK field, then
		// satisfying it through the LAST INSERT ID we already have, so we can decode it through the handlers.
		// The end result is we have the id the user wants to have returned after executing an insert.
		
		// For composite PK we take the first col for now, support for more needs research.
		$generatedFieldName = $tableSchema['pkFields'][0];
		
		// TODO: This kind of checking should be moved to the config.
		if (!isset($tableSchema['fieldIndex'][$generatedFieldName])) {
			throw new \Exception('Primary key column "' . $generatedFieldName . '" is not in the list of defined public fields.');
		}
		
		$handlerIndex = $tableSchema['fieldIndex'][$generatedFieldName];
		
		/* @var FieldHandler $handler */
		$handler = $tableSchema['fieldHandlers'][$handlerIndex][2];
		$fieldsIn = [$generatedFieldName => null];
		$fieldsOut = [];
		$handler->encodeClause($sqlContext, $fieldsIn, $fieldsOut);
		
		// We only support the basic scenario where the PK is mapped to a select of a single simple column, so we check
		// if that's what the handler produced.
		if (count($fieldsOut) != 1 || reset($fieldsOut) !== null) {
			// TODO: Word this error message better.
			throw new \Exception('The handler for the given generated PK field "" produced a complex select clause that we don\'t support for decoding generated PK from an insert statement.');
		}
		
		$internalFieldName = key($fieldsOut); // We select first key after the reset() above.
		
		// We simulate a select query for the column specified in $fieldsOut, with one row in the result set.
		$rowsIn = [[$internalFieldName => $generatedValue]];
		$rowsOut = [];
		$handler->decodeRows($fieldsIn, $rowsIn, $rowsOut);
		
		if (!isset($rowsOut[0][$generatedFieldName])) {
			// This shouldn't occur, unless the handler is buggy, but it's best to be clear.
			throw new \Exception('The handler for the generated PK field didn\'t produce a row containing it.');
		}
		
		return $rowsOut[0][$generatedFieldName];
	}
	
	// $mask is 0 when fetching handlers for decodeRows() only.
	protected function getHandlersFor($mask, $tableSchema, $fields) {
		$fieldHandlers = $tableSchema['fieldConfigs'];
		$fieldIndex = $tableSchema['fieldIndex'];
		$selectedHandlers = [];
		
		foreach ($fields as $field => $value) {
			if (isset($fieldIndex[$field])) {
				$index = $fieldIndex[$field];
				
				if (!isset($selectedHandlers[$index])) {
					$handler = $fieldHandlers[$index];
					$handlerMask = $handler->getMask();
					
					if ($mask && ($handlerMask & $mask) == 0) {
						throw new \Exception(
							'Handler for field "' . $field . '" in table ' . 
							$this->tableNameForDisplay($tableSchema) . ' is not designed to be used in context: ' . InternalUtils::contextMaskToString($mask) . '.'
						);
					}
					
					$selectedHandlers[$index] = $handler;
				}
			} else {
				throw new \Exception(
					'Undeclared field "' . $field . '" for table ' . 
					$this->tableNameForDisplay($tableSchema) . '.'
				);
			}
		}
		return $selectedHandlers;
	}
	
	protected function splitRowByPK($tableSchema, $row) {
		if ($tableSchema['pkFields'] === null) throw new \Exception('The operation cannot be performed as table ' . $tableSchema['name'] . ' has no defined primary key column(s).');
		
		// TODO: Support composite PK.
		if (count($tableSchema['pkFields'] === null) > 1) throw new \Exception('No support for composite PK yet.');
		
		$pkFields = $tableSchema['pkFields'];
		$pkFirstField = $pkFields[0];
		
		if (isset($row[$pkFirstField]) || key_exists($pkFirstField, $row)) {
			$pkValue = $row[$pkFirstField];
			unset($row[$pkFirstField]);
			return [[$pkFirstField => $pkValue], $row];
		}
	}
	
	protected function getTableSchema($tableName) {
		if (!isset($this->schema['tables'][$tableName])) throw new \Exception('Table name ' . $tableName . ' is not defined in the given schema.');
		return $this->schema['tables'][$tableName];
	}
	
	protected function tableNameForDisplay($tableSchema) {
		$exName = $tableSchema['name'];
		$inName = $tableSchema['internalName'];
		if ($exName !== $inName) $name = '"' . $exName . '" ("' . $inName . '")'; else $name = '"' . $exName . '"';
		return $name;
	}
	
	protected function fieldNamesForDisplay($fieldNames) {
		return '"' . implode('", "', $fieldNames) . '"';
	}
}