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

// FIXME: In some clauses (and statement classes) it's not very strict where you can pass keys/values/expressions with
// certain interpretations, and we need to lock down and define all this a little bit better.
// FIXME: We should probably require that columns in read context specify alias always (alias.column) to avoid
// ambiguity. Implicit cols derived from fields by prepending "this." would still work. At the same time we need to 
// enforce write context columns (i.e.: in SELECT name as _here_, or SET _here_ = val, or VALUES (_here_ = val)) are
// always without alias as aliases don't apply there. Not that we can't strip the alias from "this." (like we do) but
// it shows this can be better specified and locked down.
// TODO: The original plan was to compile the schema to something that's easily serializable, so the weight of the 
// schema object is irrelevant for reuse, because we just use it to export and serialize an array config. But we use
// closures and objects with injected (and non-serializable) state extensively for codecs and so on.
// So another option is lazy loading. We should implement setLoader() or something like this, where a closure can be
// given a recordset and it loads it on demand. We can even have field lazy loading if it works fine. We can combine
// this with serialization maybe (serialized cached skeleton config, with a single closure to autoload the rest as 
// needed on demand).
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

	// The method is named ugly like this as we're planning an insert() method with a full InsertStatement, which
	// supports some extra options like deferred insert, extended insert (multiple rows), options for results etc.
	function insertOne($rsetName, $record, $DO_NOT_SET = 'no more multi-insert') {
		if ($DO_NOT_SET !== 'no more multi-insert') throw new \Exception('Multiple insert records are no longer supported.');
		
		$rsetSchema = $this->getRecordSetSchema($rsetName);
		$sess = $this->sqlSess;
		
		// TODO: Support creating linked rows (inferred from colNamespace) i.e. deep create. We have to consider how to
		// support also deep update and deep delete before that, for consistency.
		// TODO: Support multi-insert, with a switch whether they want lastInsertId for every row or for neither.
		// There are differences in behavior across databases that need to be ironed out, and Sidekick needs work to support
		// this reliably as well. It seems MySQL returns first id on extended insert, and rest are consecutive:
		// https://dev.mysql.com/doc/refman/5.6/en/innodb-auto-increment-configurable.html <-- research depends on lock
		// settings. We can always fall back to individual inserts for DB without solid multi-insert with last id support.
		$sql = $this->renderInsertStatement($sess, $rsetSchema, ['valuesFields' => $record]);
		$sess->execute($sql);
		
		if ($rsetSchema['identityField']) {
			return $this->decodeIdentity($sess, $rsetSchema, $sess->getLastIdentity());
		}
	}
	
	function update($rsetName) {
		$rsetSchema = $this->getRecordSetSchema($rsetName);
		$sess = $this->sqlSess;
		
		return new UpdateStatement(function ($stmt) use ($rsetSchema, $sess) {
			$sql = $this->renderUpdateStatement($sess, $rsetSchema, $stmt);
			return $sess->execute($sql);
		});
	}
	
	function updateByPK($rsetName, $record) {
		$rsetSchema = $this->getRecordSetSchema($rsetName);
		list($where, $set) = $this->splitRowByPK($rsetSchema, $record);
		return $this->update($rsetName)->set($set)->where($where)->execute();
	}
	
	function query($rsetName) {
		$rsetSchema = $this->getRecordSetSchema($rsetName);
		$sess = $this->sqlSess;
		
		return new QueryStatement(function ($stmt, $getAll, $fieldOrIndex) use ($rsetSchema, $sess) {
			$sql = $this->renderQueryStatement($sess, $rsetSchema, $stmt);
			$resultSet = $sess->query($sql);
			
			// TODO: Implement. We can't naively pass this to the resultset as we're selecting fields, not columns. It's doable.
			// But it'll require extra logic to do efficiently.
			if ($fieldOrIndex !== null) throw new \Exception('Specifying field/index for getOne() is not implemented currently.');
			
			if ($getAll) {
				$rows = $resultSet->getAll($fieldOrIndex);
				$rows = $this->decodeRows($rsetSchema, $stmt['selectFields'], $rows);
				
				return $rows;
			} else {
				$row = $resultSet->getOne($fieldOrIndex);
				if ($row !== null) {
					$row = $this->decodeRows($rsetSchema, $stmt['selectFields'], [$row])[0];
				}
				
				return $row;
			}
		});
	}
	
	function queryAndUpdateByPK($rsetName) {
		$rsetSchema = $this->getRecordSetSchema($rsetName);
		$sess = $this->sqlSess;
		
		// TODO: Wrap in a nested transaction here when SqlSession support them (again).
		return new QueryAndUpdateByPKStatement(function ($stmt, $mapper) use ($rsetName, $rsetSchema, $sess) {
			// We run this in a real/virtual transaction, so we commit & fail atomically.
			$tid = $sess->begin(null, $sess::TF_VIRTUAL);
			
			try {
				// We'll be updating so we force this without making the user do it manually.
				$stmt['forShare'] = false;
				$stmt['forUpdate'] = true;
				
				$sql = $this->renderQueryStatement($sess, $rsetSchema, $stmt);
				
				// TODO: The get/update loop should stream results for a large number of results, to avoid going out of RAM.
				$rows = $sess->query($sql)->getAll();
				$rows = $this->decodeRows($rsetSchema, $stmt['selectFields'], $rows);
				
				foreach ($rows as $row) {
					$this->updateByPK($rsetName, $mapper($row));
				}
				
				$sess->commit($tid);
				return count($rows);
			} catch (\Exception $e) {
				$sess->rollback($tid);
				throw $e;
			}
		});
	}
		
	function delete($rsetName) {
		$rsetSchema = $this->getRecordSetSchema($rsetName);
		$sess = $this->sqlSess;
		
		return new DeleteStatement(function ($stmt) use ($rsetSchema, $sess) {
			$sql = $this->renderDeleteStatement($sess, $rsetSchema, $stmt);
			return $sess->execute($sql);
		});
	}
	
	protected function renderInsertStatement(SqlSession $sess, $rsetSchema, $stmt) {
		if ($rsetSchema['table'] instanceof Expr) {
			throw new \Exception('Cannot insert into a record set based on a derived table.');
		}
		
		/*
		 * Render clauses.
		 */
		
		$mask = SC::C_INSERT_STATEMENT;
		$allowJoins = false;
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		$valuesClause = $this->renderValues($sqlContext, $mask, $rsetSchema, $stmt['valuesFields']);
		
		/*
		 * Build statement.
		 */
		
		$sql = 'INSERT INTO ' . $sess->encodeName($rsetSchema['table']) . ' ';
		
		if ($valuesClause !== null) $sql .= $valuesClause . ' '; else throw new \Exception('Please specify the fields to assign via a "values" clause. You can pass an empty array to insert with all default values.');
		
		return $sql;
	}
	
	protected function renderQueryStatement(SqlSession $sess, $rsetSchema, $stmt) {
		/*
		 * Render clauses.
		 */
		
		$mask = SC::C_QUERY_STATEMENT;
		$allowJoins = true;
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		$selectClause = $this->renderSelect($sqlContext, $mask, $rsetSchema, $stmt['selectFields']);
		$fromClause = $this->renderFrom($sqlContext, $mask, $rsetSchema['table'], 'this');
		$joinClause = $this->renderJoin($sqlContext, $mask, $rsetSchema, $usedJoins);
		$whereClause = $this->renderWhere($sqlContext, $mask, $rsetSchema, $stmt['whereFields']);
		$orderClause = $this->renderOrder($sqlContext, $mask, $rsetSchema, $stmt['orderFields']);
		
		// FIXME: Add GROUP and HAVING.
		
		/*
		 * Build statement.
		 */
		
		$sql = 'SELECT ';
		if ($stmt['distinct']) $sql .= 'DISTINCT ';
		if ($stmt['distinctRow']) $sql .= 'DISTINCTROW ';
		
		if ($selectClause !== null) $sql .= $selectClause . ' '; else throw new \Exception('Please specify the fields to read via select().');
		
		$sql .= 'FROM ' . $fromClause . ' ';
		
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
	
	protected function renderUpdateStatement(SqlSession $sess, $rsetSchema, $stmt) {
		// TODO: Some situations may allow for updating derived. Research. 
		if ($rsetSchema['table'] instanceof Expr) {
			throw new \Exception('Cannot update a record set based on a derived table.');
		}
		
		/*
		 * Render clauses.
		 */
		
		$mask = SC::C_UPDATE_STATEMENT;
		$allowJoins = false; // TODO: Support join.
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		$setClause = $this->renderSet($sqlContext, $mask, $rsetSchema, $stmt['setFields']);
		$whereClause = $this->renderWhere($sqlContext, $mask, $rsetSchema, $stmt['whereFields']);
		
		/*
		 * Build statement.
		 */
		
		$sql = 'UPDATE ' . $sess->encodeName($rsetSchema['table']) . ' this SET ';
		
		if ($setClause !== null) $sql .= $setClause . ' '; else throw new \Exception('Please specify the fields to update via set().');
		if ($whereClause !== null) $sql .= 'WHERE ' . $whereClause . ' ';
		
		return $sql;
	}
	
	protected function renderDeleteStatement(SqlSession $sess, $rsetSchema, $stmt) {
		// TODO: Some situations may allow for deleting from derived. Research. 
		if ($rsetSchema['table'] instanceof Expr) {
			throw new \Exception('Cannot delete from a record set based on a derived table.');
		}
		
		/*
		 * Render clauses.
		 */
		
		$mask = SC::C_DELETE_STATEMENT;
		$allowJoins = false; // TODO: Support join.
		$usedJoins = [];
		$sqlContext = new SqlContext($sess, $mask, $allowJoins, $usedJoins);
		
		// To avoid deleting full tables by accident. Might remove this.
		if (!$stmt['whereFields']) throw new \Exception('Please specify the rows to delete via where().');
		
		$whereClause = $this->renderWhere($sqlContext, $mask, $rsetSchema, $stmt['whereFields']);
		
		// TODO: Support joins? More specific error?
		if ($usedJoins) throw new \Exception('A requested field needs joins, and DELETE statement doesn\'t support joins.');
		
		/*
		 * Build statement.
		 */
		
		$tableEn = $sess->encodeName($rsetSchema['table']);
		
		$sql = 'DELETE FROM this USING ' . $tableEn . ' this WHERE ' . $whereClause . ' ';
		
		return $sql;
	}
	
	protected function renderSelect(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_SELECT;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		if (!$columns) return null;
		
		$expr = [];
		
		foreach ($columns as $key => $val) {
			// If the value is null, we render the key as a straight column select:
			// SELECT `key`.
			if ($val === null) {
				$expr[] = $sqlContext->encodeName($key, true);
			} 
			
			// If the value is a string, we render as an alias:
			// SELECT `val` AS `key`.
			elseif (is_string($val)) {
				// We detect constructs like "sameName" => "alias.sameName" as we can render it without an explicit alias.
				// TODO: Optimize this. We can do it w/o regex (if faster).
				if (preg_match('(\w+\.' . preg_quote($key) . ')', $val)) {
					$expr[] = $sqlContext->encodeName($val, true);
				} else {				
					$expr[] = $sqlContext->encodeName($val, true) . ' AS ' . $sqlContext->encodeName($key);
				}
			}
			
			// If the value is an expression, we render it and assign the result the key as an alias (subject is null):
			// SELECT expr AS `key`.
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, null) . ' AS ' . $sqlContext->encodeName($key);
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a SELECT clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(', ', $expr);
	}
	
	protected function renderWhere(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_WHERE;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		if (!$columns) return null;
		
		$expr = [];
		
		foreach ($columns as $key => $val) {
			// Scalars and null produce a basic ident-to-value equality comparison:
			// WHERE `key` = "val"
			if ($val === null || is_scalar($val)) {
				$expr[] = $sqlContext->encodeName($key, true) . ' = ' . $sqlContext->encodeValue($val);
			} 
			
			// Expressions are rendered with the key encoded as an identifier for subject.
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, $sqlContext->encodeName($key, true));
			} 
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a WHERE clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(' AND ', $expr);
	}
	
	protected function renderGroup(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_GROUP;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		if (!$columns) return null;
		
		$mask = $maskPrev;
		throw new \Exception('Pending implementation...');
	}
	
	protected function renderHaving(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_HAVING;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		
		$mask = $maskPrev;
		throw new \Exception('Pending implementation...');
	}
	
	protected function renderOrder(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_ORDER;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		if (!$columns) return null;
		
		$expr = [];
		
		foreach ($columns as $key => $val) {
			// If the value is null or string ASC, we render a simple order-by-ident expression (null is same as ASC):
			// ORDER BY `key` val.
			if ($val === null || $val === 'ASC') {
				$expr[] = $sqlContext->encodeName($key, true);
			} 
			
			// And similar for DESC:
			// ORDER BY `key` DESC.
			elseif ($val === 'DESC') {
				$expr[] = $sqlContext->encodeName($key, true) . ' DESC';
			}
			
			// If the value is an expression, we render it and give it the encoded key as subject:
			// SELECT expr AS `key`.
			elseif ($val instanceof Expr) {
				$expr[] = $val->render($sqlContext, $sqlContext->encodeName($key, true));
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in an ORDER clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(', ', $expr);
	}
	
	// TODO: Extended insert?
	protected function renderValues(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_VALUES;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		
		// TRICKY: We explicitly check for null, as empty array is allowed (generates INSERT INTO ... DEFAULT VALUES).
		if ($columns === null) return null; 
		if (!$columns) return $sqlContext->getServerType() === 'mysql' ? 'VALUES ()' : 'DEFAULT VALUES';
		
		$cols = [];
		$vals = [];
		
		foreach ($columns as $key => $val) {
			$cols[] = $sqlContext->encodeName($key, true);
				
			// If the value is a scalar or null it's rendered as a simple col-set-to-value (null becomes SQL NULL):
			// (`key`, ...) VALUES ("value", ...)
			if ($val === null || is_scalar($val)) {
				$vals[] = $sqlContext->encodeValue($val);
			} 
			
			// If the value is an expression, we render it and give it the encoded key as subject (typically ignored):
			// (`key`, ...) VALUES (expr, ...)
			elseif ($val instanceof Expr) {
				$vals[] = $val->render($sqlContext, $sqlContext->encodeName($key, true));
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a VALUES clause.');
			}
		}
		
		//var_dump(['VALUES' => $fields, 'SQL' => [$columns, $cols, $vals]]);
		$mask = $maskPrev;
		return '(' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
	}
	
	protected function renderSet(SqlContext $sqlContext, & $mask, $rsetSchema, $fields) {
		$maskPrev = $mask; $mask |= SC::C_SET;
		$columns = $this->encodeClause($sqlContext, $rsetSchema, $fields);
		if (!$columns) return null;
		
		$expr = [];
		
		foreach ($columns as $key => $val) {
			// If the value is a scalar or null it's rendered as a simple col-set-to-value (null becomes SQL NULL):
			// SET `key` = "value"
			if ($val === null || is_scalar($val)) {
				$expr[] = $sqlContext->encodeName($key, true) . ' = ' . $sqlContext->encodeValue($val);
			} 
			
			// If the value is an expression, we render it as a col-set-to-expression and give it the encoded key as
			// subject (typically ignored):
			// SET `key` = expr
			elseif ($val instanceof Expr) {
				$expr[] = $sqlContext->encodeName($key, true) . ' = ' . $val->render($sqlContext, null);
			}
			
			else {
				throw new \Exception('Unexpected value format for field "' . $key . '" in a SET clause.');
			}
		}
		
		$mask = $maskPrev;
		return implode(', ', $expr);
	}
	
	// FIXME: Doesn't trigger the record handler, it should. We should figure out a consistent $columnsOut format first.
	protected function renderJoin(SqlContext $sqlContext, & $mask, $rsetSchema, $usedJoins) {
		if (!$usedJoins) return null;
		
		$maskPrev = $mask; $mask |= SC::C_JOIN;
		
		$expr = [];
		$joins = $rsetSchema['joins'];
		
		foreach ($usedJoins as $joinAlias => $nothing) {
			if (!isset($joins[$joinAlias])) throw new \Exception('The statement refers to non-existing join alias "' . $joinAlias . '".');
			
			list($type, $table, $condition) = $joins[$joinAlias];
			
			$join = $type . ' JOIN ' . $this->renderFrom($sqlContext, $maskPrev, $table, $joinAlias) . ' ';
			
			if ($type !== 'CROSS' || $type !== 'NATURAL') {
				if ($condition instanceof Expr) {
					$join .= $condition->render($sqlContext, null);
				} else {
					$matches = [];
					
					// TODO: Support columns with table aliases here that can trigger a join (recursive join conditions).
					// For now we assume keys implicitly refer to the root table ("this") and the values refer to the newly
					// joined table.
					$thisEn = $sqlContext->encodeName('this');
					$thatEn = $sqlContext->encodeName($joinAlias);
					
					// Should we support [$key => new Expr()]? Probably. For now, we don't.
					foreach ($condition as $key => $val) {
						if (is_int($key)) {
							$valEn = $sqlContext->encodeName($val);
							$matches[] = $thisEn . '.' . $valEn . ' = ' . $thatEn . '.' . $valEn;
						} else {
							$matches[] = $thisEn . '.' . $sqlContext->encodeName($key) . ' = ' . $thatEn . '.' . $sqlContext->encodeName($val);
						}
					}
					
					$join .= 'ON ' . implode(' ', $matches);
				}
			}
			
			$expr[] = $join;
		}
		
		$mask = $maskPrev;
		return implode(' ', $expr);
	}
	
	// FIXME: Doesn't trigger the record handler, it should. We should figure out a consistent $columnsOut format first.
	protected function renderFrom(SqlContext $sqlContext, & $mask, $table, $alias = null) {
		$maskPrev = $mask; $mask |= SC::C_FROM;
		
		if ($table instanceof Expr) {
			$sql = '(' . $table->render($sqlContext, null) . ')';
		} else {
			if ($table === $alias) $alias = null; // This happens sometimes, we can eliminate the alias when it does.
			$sql = $sqlContext->encodeName($table);
		}
		
		if ($alias !== null) {
			if ($alias === 'this') $sql .= ' this';
			else $sql .= ' ' . $sqlContext->encodeName($alias);
		}
		
		$mask = $maskPrev;
		return $sql;
	}
	
	protected function encodeClause(SqlContext $sqlContext, $rsetSchema, $fieldsIn) {
		$mask = $sqlContext->getMask();
		$columnsOut = [];
		
		// TODO: Optimize?
		if (isset($rsetSchema['recordCodec'])) {
			/* @var $recordCodec Codec */
			$recordCodec = $rsetSchema['recordCodec'];
			
			if (($recordCodec->getMask() & $mask) === $mask) {
				$recordCodec->encodeClause($sqlContext, $fieldsIn, $columnsOut);
			}
		}
		
		if ($fieldsIn) {
			$codecs = $this->getCodecsFor($mask, $rsetSchema, $fieldsIn);
			
			/* @var Codec $codec */
			foreach ($codecs as $codec) {
				$codec->encodeClause($sqlContext, $fieldsIn, $columnsOut); 
			}
		}
		
		return $columnsOut;
	}
	
	protected function decodeRows($rsetSchema, $selectedFields, $rowsIn) {
		$recordsOut = [];
		
		if ($selectedFields) {
			$codecs = $this->getCodecsFor(0, $rsetSchema, $selectedFields);
		
			/* @var Codec $codec */
			foreach ($codecs as $codec) {
				$codec->decodeRows($selectedFields, $rowsIn, $recordsOut);
			}
		}
	
		// TODO: Optimize? We can have a flag for this in the mask which is used only for decoding (for all codecs).
		if (isset($rsetSchema['recordCodec'])) {
			/* @var $recordCodec Codec */
			$recordCodec = $rsetSchema['recordCodec'];
			$recordCodec->decodeRows($selectedFields, $rowsIn, $recordsOut);
		}
		
		return $recordsOut;
	}
	
	// FIXME: We don't trigger the record handler while decoding identity. Should we? Decide and document.
	protected function decodeIdentity(SqlSession $sqlSession, $rsetSchema, $identityValue) {
		$identityFieldName = $rsetSchema['identityField'];
		$identityColumnName = $rsetSchema['identityColumn'];
		
		// TODO: This kind of processing is seen frequently in both Sidekick and Codec instances. It should be moved to
		// a public Utils class (with option whether we want the col to have table alias in, stripped, or stripped if 
		// 'this' only).
		if ($identityColumnName === null) {
			$identityColumnName = $identityFieldName;
		} else {
			$pos = strpos($identityColumnName, '.');
			if ($pos !== false) $identityColumnName = substr($identityColumnName, $pos + 1);
		}
		
		// TODO: This kind of checking should be moved to the config.
		if (!isset($rsetSchema['fieldIndex'][$identityFieldName])) {
			throw new \Exception('Identity field "' . $identityFieldName . '" is not in the list of defined public fields.');
		}
		
		/* @var Codec $codec */
		$codecIndex = $rsetSchema['fieldIndex'][$identityFieldName];
		$codec = $rsetSchema['fieldConfigs'][$codecIndex];
		$fieldsIn = [$identityFieldName => null];
		$name = $rsetSchema['name'];
		
		// We simulate a select query result set for the column we have.
		$rowsIn = [[$identityColumnName => $identityValue]];
		$recordsOut = [];
		$codec->decodeRows($fieldsIn, $rowsIn, $recordsOut);
		
		if (!isset($recordsOut[0][$identityFieldName])) {
			// This shouldn't occur, unless the codec is buggy, but it's best to be clear.
			throw new \Exception('The codec for the identity field didn\'t produce a row containing it.');
		}
		
		return $recordsOut[0][$identityFieldName];
	}
	
	protected function getCodecsFor($mask, $rsetSchema, $fields) {
		$fieldCodecs = $rsetSchema['fieldConfigs'];
		$fieldIndex = $rsetSchema['fieldIndex'];
		$selectedCodecs = [];
		
		foreach ($fields as $field => $value) {
			if (isset($fieldIndex[$field])) {
				$index = $fieldIndex[$field];
				
				if (!isset($selectedCodecs[$index])) {
					$codec = $fieldCodecs[$index];
					$codecMask = $codec->getMask();
					
					// $mask is null when fetching codecs for decodeRows() only.
					if ($mask !== null && ($codecMask & $mask) !== $mask) {
						// TODO: Be more specific which part of the mask doesn't match (clause or statement).
						throw new \Exception(
							'Codec for field "' . $field . '" in record set ' . 
							$this->rsetNameForDisplay($rsetSchema) . ' is not designed to be used in context: ' . CodecUtils::contextMaskToString($mask) . '.'
						);
					}
					
					$selectedCodecs[$index] = $codec;
				}
			} else {
				throw new \Exception(
					'Undeclared field "' . $field . '" for record set ' . 
					$this->rsetNameForDisplay($rsetSchema) . '.'
				);
			}
		}
		
		return $selectedCodecs;
	}
	
	protected function splitRowByPK($rsetSchema, $record) {
		$pkFieldSets = $rsetSchema['pkFieldSets'];
		
		if ($pkFieldSets === null) {
			throw new \Exception('The operation cannot be performed as record set ' . $this->rsetNameForDisplay($rsetSchema) . ' has no defined primary key column(s).');
		}
		
		$pk = [];
		
		// TODO: We can possibly optimize this when we have an identity col only (not even produce the $pkFieldSets in config)?
		foreach ($pkFieldSets as $pkFieldSet) {
			foreach ($pkFieldSet as $pkField) {
				if (isset($record[$pkField]) || key_exists($pkField, $record)) {
					$pk[$pkField] = $record[$pkField];
					unset($record[$pkField]);
				} else {
					$pk = [];
					continue 2;
				}
			}
			
			if ($pk) return [$pk, $record];
		}
		
		throw new \Exception('The operation cannot be performed as the given record(s) for record set ' . $this->rsetNameForDisplay($rsetSchema) . ' don\'t contain a valid set of fields specifying the full primary key.');
	}
	
	protected function getRecordSetSchema($rsetName) {
		if (!isset($this->schema['recordSets'][$rsetName])) throw new \Exception('Record set name ' . $rsetName . ' is not defined in the given schema.');
		return $this->schema['recordSets'][$rsetName];
	}
	
	protected function rsetNameForDisplay($rsetSchema) {
		$rsetName = '"' . $rsetSchema['name'] . '"';
		$table = $rsetSchema['table'];
		$tableName = $table instanceof Expr ? 'derived table' : 'table "' . $table . '"';
		if ($rsetName !== $tableName) $name = $rsetName . ' (' . $tableName . ')'; else $name = $rsetName;
		return $name;
	}
	
	protected function fieldNamesForDisplay($fieldNames) {
		return '"' . implode('", "', $fieldNames) . '"';
	}
}