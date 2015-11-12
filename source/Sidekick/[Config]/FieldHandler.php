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

/**
 * A handler which interprets one or more fields that map to one or more table columns (or SQL expressions).
 */
interface FieldHandler {
	/**
	 * Returns a bitmask combining SqlContext's S_* and C_* constants, for statements and clauses where the fields of
	 * this handler are valid. If a caller attempts to use a field in a context that doesn't match this mask, Sidekick
	 * will	throw an exception.
	 * 
	 * The P_* constants don't apply here as fields only exist as a concept in the P_EXECUTE phase, so you don't need
	 * to explicitly set on a P_* flag in the returned mask.
	 * 
	 * @return int
	 */
	function getMask();
	
	/**
	 * Returns a list of field names (public names, not internal columns) that this object will handle.
	 * 
	 * @return array
	 * list<string>;
	 */
	function getHandledFields();
	
	/**
	 * Encodes fields in various SQL clauses for statement generation.
	 * 
	 * TODO: Document how the rows are interpreted differently for every clause type.
	 * 
	 * The encoder should write the output only for the field(s) it represents, leaving the rest of the output
	 * untouched. 
	 * 
	 * @param SqlContext $sqlContext
	 * SQL context, used to encode values and columns for the relevant SQL connection, retrieve the current statement
	 * and clause type, etc.
	 * 
	 * @param array $fieldsIn
	 * dict; A dict of fields. Implementations should expect values may be Expr instances. The contained values in an 
	 * Expr instance can be processed through method transformed();
	 * 
	 * @param array & $columnsOut
	 * dict; A dict of output columns by reference. The output may be empty or contain partial column set written by
	 * other field handlers.
	 */
	function encodeClause(SqlContext $sqlContext, $fieldsIn, & $columnsOut);
		
	/**
	 * Decodes SQL result set (as a list of rows) to a list of records consumable by the Sidekick user.
	 * 
	 * The decoder should write the output only for the field(s) it represents, leaving the rest of the output
	 * untouched.
	 * 
	 * We pass an entire list here as the results are a lot more homogenous than the query clauses, so batching
	 * opportunities are much stronger here.
	 * 
	 * @param array $selectedFields
	 * dict; The original select clause fields that produced the given input rows.
	 * 
	 * @param array $rowsIn
	 * list<dict>; "Raw" result set rows from the query.
	 * 
	 * @param array & $recordsOut
	 * list<dict>; A reference to the decoded output records. The output may be empty or contain partial records written
	 * by other field handlers.
	 */
	function decodeRows($selectedFields, $rowsIn, & $recordsOut);
}