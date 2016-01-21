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
class RecordSet {
	/**
	 * Name for this record set (client-side only).
	 */
	protected $name = null;
	
	/**
	 * Table name as a string, or a SELECT query for a derived table.
	 * 
	 * @var string|Expr
	 */
	protected $table = null;
	
 	protected $pkFieldSets = null;
 	protected $identityField = null; 
 	protected $identityColumn = null; 	
 	protected $recordCodec = null;
	
	/**
	 * list<Codec>;
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
	 * - type: string; Uppercase string containing the join type: INNER, LEFT, RIGHT, NATURAL, CROSS.
	 * - table: string|Expr; Full table name (or derived table SELECT query) we're joining.
	 * - condition: array|Expr|null; A list/dict specifying columns to match or expression as a join condition.
	 * 
	 * Note that the condition is always null (no condition) for NATURAL and CROSS joins, and never null for the rest.
	 * 
	 * @var array
	 */
	protected $joins = []; // TODO: Document format.
	
	/**
	 * @param string $name
	 * A unique string name for the record set. This name is only ever used in PHP (not in the generated SQL queries),
	 * so there are no restrictions placed on its format and charset.
	 * 
	 * @param string|Expr $table
	 * Either a string pointing to the full SQL table name this recordset represents, or an Expr instance rendering
	 * a select query, which creates a record set based on a derived table (based on the query).
	 */
	function __construct($name, $table) {
		$this->name = $name;
		$this->table = $table ?: $name;
	}
	
	/**
	 * Sets one or more valid sets of fields that encode to the primary column(s) for the table.
	 * 
	 * See also setIdentityField().
	 * 
	 * IMPORTANT: Read the documentation of the arguments carefully, as it can be tricky to get right at first.
	 * 
	 * @param string|list<string> ...$pkFieldSets
	 * One or more arguments, where every argument is a string field name, or a list of field names that encode to the 
	 * represented table's primary key (composite primary keys are supported, both on the field side & the column side).
	 * 
	 * Do note that become multiple fields may encode to the same primary keys columns, we allow alternate field sets
	 * to describe the primary key. It's very important whether you are passing multiple string arguments, or a single
	 * array of strings. For example, let's take two examples:
	 * 
	 * - 1) setPKFieldSets('a', 'b', 'c')
	 * - 2) setPKFieldSets(['a', 'b', 'c'])
	 * 
	 * Here's what they mean. An input record is considered to contain a full primary key if it contains...
	 * 
	 * - 1) Field "a" OR "b" OR "c". Either one of those encodes to the primary key column(s) for the table.
	 * - 2) Fields "a" AND "b" AND "c". All those TOGETHER encode to the primary key column(s) for the table.
	 * 
	 * A more advanced example may read:
	 * 
	 * - setPKFieldSets('allBits', ['lowBits', 'highBits'])
	 * 
	 * This is interpreted to mean that a record contains a full primary key if it contains:
	 * 
	 * - Field "allBits" OR (field "lowBits" AND field "highBits").
	 * 
	 * This allows full flexibility in how you encode primary keys as fields, and Sidekick commands that require records
	 * with a primary key will work with any valid combination.
	 * 
	 * @param string $insertIdField
	 * Optional (default = null). Set this to a field name to use to decode the last id produced after an insert
	 * (autoincrement in MySQL, SERIAL in PgSQL etc.). If you leave this parameter to null, Sidekick::insert() will not
	 * return the new id for an inserted record.
	 * 
	 * IMPORTANT: If you have a composite primary key, specify the autoincrementing column first in parameter $columns,
	 * as this is the column we assume decodes to the field set here.
	 * 
	 * TODO: Support multiple sequences in one insert?
	 * 
	 * TODO: Add optional 3rd param to specify sequence name, instead of autoincrement/SERIAL type.
	 */
	function setPKFieldSets(...$pkFieldSets) {
		// TODO: Validate in render() that those PK fields set here exist.
		foreach ($pkFieldSets as & $pkFieldSet) {
			$pkFieldSet = (array) $pkFieldSet;
		}
		$this->pkFieldSets = $pkFieldSets;
		
		return $this;
	}
	
	/**
	 * Specifies an "identity field" which maps to an auto-incrementing column for the represented table.
	 * 
	 * If you specify an identity field via this method, Sidekick::insert() will return the identity for the newly 
	 * created record/row, filtered through the decoder for the field specified here.
	 * 
	 * Note that if you specify an identity field, it's also considered to be the primary key for the table, unless you
	 * override this decision by calling setPKFieldSets().
	 * 
	 * You need to use setPKFieldSets() if you have composite primary key (composed of multiple fields that should
	 * appear together in a record), or if you have multiple encodings of your identity column as alternate fields, 
	 * either one of which maps to the same identity column on the database side.
	 * 
	 * @param string $identityField
	 * 
	 * @param string|null $identityColumn
	 * Optional. If you don't give a value (or give null) the column is assumed to have the same name as the field.
	 * 
	 * @return \Solver\Sidekick\RecordSet
	 */
	function setIdentityField($identityField, $identityColumn = null) {
		$this->identityField = $identityField;
		$this->identityColumn = $identityColumn;
		if ($this->pkFieldSets === null) $this->pkFieldSets = [[$identityField]];
		
		return $this;
	}
	
	/**
	 * Registers a codec as a record-wide codec. This means:
	 * 
	 * - This codec's encodeClause() runs *before* any other field codecs.
	 * - This codec's decodeRows() runs *after* any other field codecs.
	 * 
	 * Note that calling setRecordCodec() multiple times overwrites the previous codec. There is only one record
	 * codec per record set.
	 * 
	 * The codec is always invoked never mind what fields are set, as long as the mask of the codec matches the
	 * context.
	 * 
	 * @param Codec $codec
	 * 
	 * @return \Solver\Sidekick\RecordSet
	 */
	function setRecordCodec(Codec $codec) {
		$this->recordCodec = $codec;
		
		return $this;
	}
	
	// Codecs added here must return one or more fields from getField() and will be triggered only when that field is
	// used in some context.
	function addFieldCodecs(Codec ...$codecs) {
		$count = count($this->fieldConfigs);
		
		foreach ($codecs as $codec) {
			$fields = $codec->getFields();
			if (!$fields) throw new \Exception('Codecs used in "field codec" context must return one or more fields from getFields().');
			
			foreach ($fields as $field) {
				if (isset($this->fieldIndex[$field])) throw new \Exception('Duplicate declaration of field "' . $field . '".');
				$this->fieldIndex[$field] = $count;
			}
			
			$this->fieldConfigs[] = $codec;
			$count++;
		}
		
		return $this;
	}
	

	function addInnerJoin($name, $table, $condition) {
		return $this->addJoin('INNER', $name, $table, $condition);
	}
	
	function addLeftJoin($name, $table, $condition) {
		return $this->addJoin('LEFT', $name, $table, $condition);		
	}
	
	function addRightJoin($name, $table, $condition) {
		return $this->addJoin('RIGHT', $name, $table, $condition);		
	}
	
	function addNaturalJoin($name, $table) {
		return $this->addJoin('NATURAL', $name, $table, null);
	}
	
	function addCrossJoin($name, $table) {
		return $this->addJoin('CROSS', $name, $table, null);
	}
	
	// FIXME: There's no good way to join MxN junction table as we don't support joins triggering other joins. We need
	// to support this.
	//
	// TODO: Document every public add*Join() method based on this one.
	//
	// $table can be string table name OR Expr SELECT query (for derived). If null, same as $name.
	//
	// $condition can be (note it's COLUMNS, not FIELDS for the condition; we don't expose the condition to users, so we
	// don't waste time mapping it from fields):
	// - Same col names on both sides, one or more: [col1, col2] rendered ON foo.col1 = bar.col1 AND foo.col2 = bar.col2
	// - Differing col names: [col1 => col2, col3 => col4] rendered ON foo.col1 = bar.col2 AND foo.col3 = bar.col4
	// - Expression (rendered verbatim) ON $expr->render().
	protected function addJoin($type, $name, $table, $condition) {
		if (is_array($condition)) foreach ($condition as $key => $val) if (is_int($key)) {
			unset($condition[$key]);
			$condition[$val] = $val;
		}
		
		$table = $table ?: $name;
		if ($name === 'this') throw new \Exception('Alias "this" is reserved to refer to the base table for a recordset.');
		
		if (isset($this->joins[$name])) throw new \Exception('Duplicate declaration of join named "' . $name . '".');
		$this->joins[$name] = [$type, $table, $condition];
		
		return $this;
	}
	
	function render() {
		return get_object_vars($this);
	}

// 	function addFieldLoader(\Closure $closure) {
// 		TODO: Ability to lazy-load a field upon it being demanded (allows schemas to not get too big up-front).	
//		This might not be too effective for fields, but it'll work fine for RecordSet objects (lazy loading them).
// 	}
}