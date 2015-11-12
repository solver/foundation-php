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

// TODO:
// Read fields: only can be selected;
// Write fields: only can be written to;
// Conditions or filter fields: only can be used in where() clauses;
// Expression-based fields can have parameters;
// Linked is just another field type (with parameter Selector).
// Expressions get QueryBuilder where they can add to the select etc. query (and say, add JOIN and get back temp table id to use etc.).
class Table {
	protected $name = null;	
	protected $internalName = null;
	
 	protected $pkFields = null; 	
 	protected $pkFieldIsGenerated = false;
	
	/**
	 * list<FieldHandler>;
	 * 
	 * @var array
	 */
	protected $fieldConfigs = [];
	
	/**
	 * dict<fieldName: string, fieldConfigIndex: int>; The index points to property $fieldConfigs.
	 * 
	 * @var array
	 */
	protected $fieldIndex = [];
	
	/**
	 * dict<tableAlias: string, joinConfig: #JoinConfig>;
	 * 
	 * #JoinConfig: tuple...; Describes a table join config.
	 * - table: string; Full table name we're joining.
	 * - condition: array|Expr; A list/dict specifying columns to match or expression to render as a join condition.
	 * 
	 * @var array
	 */
	protected $joins = []; // TODO: Document format.
	
	function __construct($name, $internalName = null) {
		$this->name = $name;
		$this->internalName = $internalName ?: $name;
	}
	
	/**
	 * Sets the primary key for the table.
	 * 
	 * @param string|list<string> $fieldOrFieldList
	 * A string field name to set as a primary key, or a list of fields for a composite primary key.
	 * 
	 * @param bool $isGenerated
	 * Optional (default = false). True if the primary key is generated (autoincrement in MySQL, SERIAL in PgSQL etc.),
	 * false if not.
	 * 
	 * Passing true means the insert command will return the last insert id, mapped according to the transform rules
	 * for the specified primry key column.
	 * 
	 * Note that if you specify a composite primary key, the system assumed the first key is the one that's serial or
	 * autoincrementing (and its decoding transform is used for the return value).
	 * 
	 * TODO: Support multiple sequences in one insert?
	 * 
	 * TODO: Add optional 3rd param to specify sequence name, instead of autoincrement/SERIAL type.
	 */
	function setPK($fieldOrFieldList, $isGenerated = false) { // TODO: Document the names here should be the "external" names, to avoid confusion when they differ from the internal ones.
		// TODO: Validate in render() that those PK fields set here exist.
		$this->pkFields = (array) $fieldOrFieldList;
		$this->pkFieldIsGenerated = $isGenerated;
		return $this;
	}
	
	function addFields(FieldHandler ...$handlers) {
		$count = count($this->fieldConfigs);
		
		foreach ($handlers as $handler) {
			foreach ($handler->getHandledFields() as $field) {
				if (isset($this->fieldIndex[$field])) throw new \Exception('Duplicate declaration of field "' . $field . '".');
				$this->fieldIndex[$field] = $count;
			}
			
			$this->fieldConfigs[] = $handler;
			$count++;
		}
		
		return $this;
	}
	
	// $condition can be:
	// Same col names on both sides, one or more: [col1, col2] rendered ON foo.col1 = bar.col1 AND foo.col2 = bar.col2
	// Differing col names: [col1 => col2, col3 => col4] rendered ON foo.col1 = bar.col2 AND foo.col3 = bar.col4
	// Expression (rendered verbatim) ON $expr->render().
	function addJoin($name, $tableName, $condition) {
		if (is_array($condition)) foreach ($condition as $key => $val) if (is_int($key)) {
			unset($condition[$key]);
			$condition[$val] = $val;
		}
		
		$tableName = $tableName ?: $name;
		if ($name === 'this') throw new \Exception('Alias "this" is reserved to refer to the root table for a recordset.');
		
		if (isset($this->joins[$name])) throw new \Exception('Duplicate declaration of join named "' . $name . '".');
		$this->joins[$name] = [$tableName, $condition];
		
		return $this;
	}
	
	function render() {
		return get_object_vars($this);
	}

	
	// TODO: Allow multiple field mappings to the primary key
// 	function addAltPK($columnOrColumnList, $isGenerated = false) {
		
// 	}
	
// 	function setSuper($tableName) { TODO: Inheritance, maybe multiple (like traits); makes joins on PK if a superfield is selected.
// 		
// 	}

// 	function addFieldLoader(\Closure $closure) {
// 		TODO: Ability to lazy-load a field upon it being demanded (allows schemas to not get too big up-front).		
// 	}
	
// 	function addDirective($fieldName, $columnName = null, \Directive $directive) {
//		
// 	}
}