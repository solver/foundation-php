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

interface Expr {
	/**
	 * Returns a clone of the expression, with every value it holds processed through the given closure.
	 * 
	 * This monadic approach allows processing routines to treat expressions transparently as values, without having
	 * to render them and understand them at a deeper level.
	 * 
	 * @param \Closure $transform
	 * (value: mixed) => mixed; Takes a value, returns a transformed value.
	 * 
	 * @return Expr
	 */
	function transformed($transform);
	
	/**
	 * Renders itself against the given SQL session and returns a string.
	 * 
	 * @param SqlContext $sqlContext
	 * SQL context, used to encode values and columns for the relevant SQL connection, retrieve the current statement
	 * and clause type, etc.
	 * 
	 * @param int $stmtType
	 * Statement type where the expression will be used, one of the self::C_* constants.
	 * 
	 * @param int $clauseType
	 * Clause type where the expression will be used, one of the self::S_* constants.
	 * 
	 * @param string|null $subject
	 * A valid SQL expression which is the "subject" for this expression (typically the column the field represents).
	 * 
	 * The subject can be used in comparison operations by expressions that render comparison expressions. It should not
	 * be escaped and encoded, it's already encoded properly by the caller. 
	 * 
	 * Some expressions may not need a subject, and so may ignore this parameter.
	 * 
	 * In SELECT clauses there is no subject, so you'll receive null.
	 * 
	 * TODO: Move this to the context?
	 * 
	 * @return string
	 * A valid SQL expression string.
	 */
	function render(SqlContext $sqlContext, $subject);
}