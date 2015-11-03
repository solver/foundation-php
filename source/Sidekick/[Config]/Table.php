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

// TODO: Change terminology to "fields" which can be based on columns or expressions.
// Read fields: only can be selected;
// Write fields: only can be written to;
// Conditions or filter fields: only can be used in where() clauses;
// Expression-based fields can have parameters;
// Linked is just another field type (with parameter Selector).
// Expressions get QueryBuilder where they can add to the select etc. query (and say, add JOIN and get back temp table id to use etc.).
class Table {
	protected $hasColumnCodecs = false;
	protected $hasColumnRenames = false;
	protected $name = null;
	protected $internalName = null;
	protected $externalFieldList = [];
	protected $internalFieldList = [];
	protected $externalFields = [];
	protected $internalFields = [];
 	protected $primaryKey = null;
 	protected $primaryKeyIsGenerated = false;
	
	function __construct($name = null, $internalName = null) {
		if ($name !== null) $this->setName($name, $internalName);
	}

	function setName($name, $internalName = null) {
		$this->name = $name;
		$this->internalName = $internalName ?: $name;
		return $this;
	}
	
// 	function setSuper($tableName) { TODO: Inheritance, maybe multiple (like traits); makes joins on PK if a superfield is selected.
// 		
// 	}
	
	/**
	 * Sets the primary key for the table.
	 * 
	 * @param string|list<string> $columnOrColumnList
	 * A string column name to set as a primary key, or a list of strings for a composite primary key.
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
	function setPK($columnOrColumnList, $isGenerated = false) { // TODO: Document the names here should be the "external" names, to avoid confusion when they differ from the internal ones.
		// TODO: Validate in render() that those PK fields set here exist, and are columns, not expressions (why not expressions? think about it)
		$this->primaryKey = is_array($columnOrColumnList) ? $columnOrColumnList : [$columnOrColumnList];
		$this->primaryKeyIsGenerated = $isGenerated;
		return $this;
	}

// 	function addFieldLoader(\Closure $closure) {
// 		TODO: Ability to lazy-load a field upon it being demanded (allows schemas to not get too big up-front).		
// 	}
	
// 	function addDirective($fieldName, $columnName = null, \Directive $directive) {
//		
// 	}
	
	function addExpession($name, $internalName = null, $expression, \Closure $decoder = null) {
		// Field type pseudo-constants.
		static $FT_EXPR = 2;
		
		list($composite, $name, $internalName) = $this->normalizeFieldNames($name, $internalName);
		
		if ($composite && $decoder === null) {
			throw new \Exception('Decoder must be provided when you specify composite expressions.');
		}
		
		$publicConfig = [
			'name' => $name,
			'toName' => $internalName,
			'composite' => false,
			'transform' => null,
			'expression' => $expression,
			'type' => $FT_EXPR,
		];
		
		$internalConfig = [
			'name' => $internalName,
			'toName' => $name,
			'composite' => false,
			'transform' => $decoder,
			'type' => $FT_EXPR,
		];
		
		$this->addFieldConfig($composite, $name, $internalName, $publicConfig, $internalConfig);
		
		return $this;
	}
	
	function addColumn($name, $internalName = null, \Closure $encoder = null, \Closure $decoder = null) {
		// Field type pseudo-constants.
		static $FT_COL = 1;
		
		list($composite, $name, $internalName) = $this->normalizeFieldNames($name, $internalName);
		
		if ($composite && ($encoder === null || $decoder === null)) {
			throw new \Exception('Encoder and decoder must be provided when you specify composite columns.');
		}

		$publicConfig = [
			'name' => $name,
			'toName' => $internalName,
			'composite' => $composite,
			'transform' => $encoder,
			'type' => $FT_COL,
		];
		
		$internalConfig = [
			'name' => $internalName,
			'toName' => $name,
			'composite' => $composite,
			'transform' => $decoder,
			'type' => $FT_COL,
		];
		
		$this->addFieldConfig($composite, $name, $internalName, $publicConfig, $internalConfig);
		
		return $this;
	}
		
	function render() {
		if ($this->name === null) throw new \Exception('Table name is a required property.');
		if (!$this->externalFields) throw new \Exception('Tables need at least one column.');
		return get_object_vars($this);
	}
	
	protected function normalizeFieldNames($name, $internalName) {
		if ($internalName === null) $internalName = $name;
		$composite = is_array($name) || is_array($internalName);
		
		if ($composite) {
			$name = (array) $name;
			$internalName = (array) $internalName;
		}
		
		return [$composite, $name, $internalName];
	}
	
	protected function addFieldConfig($composite, $name, $internalName, $publicConfig, $internalConfig) {
		// TODO: Support uni-directional bindings (vs. bidirectional default now) which will allow multiple definitions
		// of one field to map FROM, as long as one is selected primary when mapping TO it.
		if ($composite) {
			foreach ($name as $v) {
				if (isset($this->externalFields[$v])) throw new \Exception('Duplicate declaration of public field "' . $v . '".');
				$this->externalFields[$v] = $publicConfig;
			}
			$this->externalFieldList[] = [true, $name];
			
			foreach ($internalName as $v) {
				if (isset($this->internalFields[$v])) throw new \Exception('Duplicate declaration of internal field "' . $v . '".');
				$this->internalFields[$v] = $internalConfig;
			}
			$this->internalFieldList[] = [true, $internalName];
		} else {
			if (isset($this->externalFields[$name])) throw new \Exception('Duplicate declaration of public field "' . $name . '".');
			$this->externalFields[$name] = $publicConfig;
			$this->externalFieldList[] = [false, $name];
			
			if (isset($this->internalFields[$internalName])) throw new \Exception('Duplicate declaration of internal field "' . $internalName . '".');
			$this->internalFields[$internalName] = $internalConfig;
			$this->internalFieldList[] = [false, $internalName];
		}
	}
}