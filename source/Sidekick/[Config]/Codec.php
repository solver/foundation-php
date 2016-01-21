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
 * Encodes records (of field) to rows (of columns that may contain values and SQL expression) and back. Combined,
 * multiple codecs that handle individual fields of a record, and overall record handling are used to build the record
 * schema.
 * 
 * TODO: We need to differentiate FieldCodec and RecordCodec.
 * - Only FieldCodec needs method getFields().
 * - FieldCodec could use some extra features like:
 * 		- Per-field mask (and getMask() can be used to trigger a filter even if a field is not present, allowing
 * 		  generated values on insert if missing, check required fields in WHERE clause if missing etc. We can have
 * 		  getFields() be a map of $fieldName => $fieldMask.
 * - RecordCodec could use some extra features like:
 * 		- Filtering input fields (by ref.), currently passed by reference so we can't do that.
 * 		- Getting all clauses together so $whereFieldsIn can affect $havingColumnsOut (for fields with complex behavior)
 * 		  or even $joinsOut for joining with the record codec. Maybe we can do this by bundling all in two simple
 * 		  objects (which are passed by object handle which solves the previous problem as well re. ability to filter
 * 		  fields).
 * 
 * TODO: Test if this alternative is faster than encodeClause() decodeRows():
 * - We have 5 methods: encodeQuery, encodeInsert, encodeUpdate, encodeDelete, decodeResultSet
 * - Each passes full objects (implicitly by ref) with public properties for every clause (including some which are only
 * in the output statements, like JOIN and FROM, not in input, as joins and from are implicitly resolved).
 * 		- encodeQuery(SqlContext $sqlContext, QueryStatement $stmtIn, QueryStatement $stmtOut
 * 		- decodeResultSet(SelectStatement $stmt, & $resultsIn, & $resultsOut)
 * 
 * It's less work checking masks etc. and we might even skip per-field existence checks and delegate them to the codecs
 * which can handle it better & more efficiently locally. But it should be built, and benched against current solutions.
 * 
 * We should also have one input-only clause in all statement, "directives" (or similar) which encodes field values that
 * perform more complex actions that can't be described simply in the other clauses (like enabling custom query 
 * processing flags that Sidekick has no native support for and it's codec-provided).
 * 
 * TODO: Have a flag where a codec may require that a transaction wrap all operations on statement execution? Allows
 * a codec to do a secondary query/cmd on the connection and have it be in the same transaction as the parent statement,
 * so they're consistent and atomic.
 * 
 * TODO: FINDINGS: I tested codec-filtered operation (codecs decide whether run or not, may run despite their field is
 * not defined and they should check if the field is defined and handle "is not" case gracefully by returning). Things
 * break even at roughly when 70% of defined field codecs should run based on input. Less: sidekick-filtered codecs are
 * faster, more: codec-filtered codecs are faster. We can have codec-filtering by default and only kick-in sidekick
 * filtering if the ratio is crossed, making it an optional optimization. We may want to do the same for masks. This
 * would also allow us to not filter at lower level like group/composite/alternate. 
 * 
 * If we filter we might need a flag bit that enables a codec to always run, if it decides so for itself, so it can
 * set a default value for itself, or check requirements.
 * 
 * TODO: We can have const MASK, which if 0, it invokes getMask(), else sticks to the constant for faster operation.
 */
interface Codec {
	/**
	 * Returns a bitmask of SqlContext's C_* constants, for statements and clauses where this codec applies. The mask
	 * is interpreted a bit differently based on the role the codec has in a RecordSet's schema:
	 * 
	 * - For field-specific codecs, if a caller attempts to use a field this codec handles in a context that doesn't
	 * match this mask, Sidekick will throw an exception.
	 * - For a record-wide codec, if the mask doesn't match for a specific clause, the codec is skipped (no errors). 
	 * 
	 * @return int
	 */
	function getMask();
	
	/**
	 * Returns a list of field names (public names, not internal columns) that this object will handle.
	 * 
	 * This is required only for codecs added as field-specific codecs in a record set's schema. For record codecs
	 * this method can return null.
	 * 
	 * @return array|null
	 * list<string>|null;
	 */
	function getFields();
	
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