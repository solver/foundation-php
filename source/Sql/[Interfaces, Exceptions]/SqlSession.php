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
namespace Solver\Sql;

/**
 * Represents an SQL session. 
 * 
 * SQL sessions are designed to always used pooled connections, so unlike a connection resource (which is not explicitly
 * exposed in this library) a session is very fast to create and dispose of. For effective pooling and isolation between
 * SQL users, it's highly recommended to fetch an SQL session at the last possible moment before you need it, and 
 * dispose of it (or close() it explicitly, if you want to store it) as soon as you're done.
 * 
 * TODO: Improve PHPDoc.
 * 
 * IMPORTANT: SqlSession should support cloning, where the clone references the same state as the original. This
 * allows reference counting with named connection in SqlPool. Such connected clones can be implemented 
 * in many ways: containing a proxy connection; having state passed in and assigned by reference to internal properties
 * (clones retain references), or creating references to the original on the first clone in a __clone call (for further
 * clones, PHP retain the reference) either by assigning all by-value properties (and object/resource handled that may 
 * have to be re-assigned) by &reference in the contructor (cloning preserves references), or by implementing __clone 
 * suitable so the clone state is linked to the original. It should be completely transparent which instance of two 
 * cloned connections is being used (except for comparing them by reference, where they will, of course, differ).
 */
interface SqlSession {
	/**
	 * Transaction fulfillment "real" requires the transaction is executed on the database connection. Nesting is not
	 * supported for this fulfillment type for most RDBMS vendors, as they don't support native nesting, so only the
	 * top-most transaction can be "real".
	 * 
	 * @var string
	 */
	const TF_REAL = 'real';
	
	/**
	 * TODO: This constant shouldn't be here if some drivers don't support it? Research if any mainstream ones don't.
	 * 
	 * Transaction fulfillment "savepoint" uses a savepoint to allow a nested transaction to be rolled back. Requires
	 * a RDBMS which supports savepoints.
	 * 
	 * When a transaction with this fulffillment is required when no real database transaction is open, it opens a real
	 * transaction instead (see self::TF_REAL).
	 * 
	 * @var string
	 */
	const TF_SAVEPOINT = 'savepoint';
	
	/**
	 * Transaction fulfillment "virtual" creates a purely virtual transaction (no action is performed on the SQL
	 * server). Rolling back a virtual transaction causes SqlSession to mark the transaction stack as "inconsistent",
	 * which makes it impossible to commit() any real or savepoint-based transaction. If a commit is attempted on, 
	 * it'll result in a rollback and a thrown exception. See isConsistent() and setConsistent() for ways to test for
	 * the consistent flag and override it when necessary.
	 * 
	 * When a transaction with this fulfillment is required when no real database transaction is open, it opens a real
	 * transaction instead (see self::TF_REAL).
	 * 
	 * @var string
	 */
	const TF_VIRTUAL = 'virtual';
	
	// Generic.
	const TI_READ_UNCOMMITTED = 'READ UNCOMMITTED';
	const TI_READ_COMMITTED = 'READ COMMITTED';
	const TI_REPEATABLE_READ = 'READ REPEATABLE';
	const TI_READ_SERIALIZABLE = 'SERIALIZABLE';
	
	// For SQLite. TODO: This might need to be a separate param. SQLite supports SERIALIZABLE/READ_UNCOMMITTED. Research.
	const TI_DEFERRED = 'DEFERRED';
	const TI_IMMEDIATE = 'IMMEDIATE';
	const TI_EXCLUSIVE = 'EXCLUSIVE';
	
	public function open();
	
	public function close();
	
	/**
	 * Runs a query, returns a result set object.
	 * 
	 * @param string $sql
	 * SQL query string to execute.
	 * 
	 * @return SqlResultSet
	 */
	public function query($sql);
	
	/**
	 * Runs a command, returns number of affected rows (or 0 when not applicable).
	 * 
	 * TODO: Should the return result be a separate method? getAffectedRows()?
	 * 
	 * @param string $sql
	 * SQL command string to execute.
	 * 
	 * @return int
	 * Number of affected rows, when applicable.
	 */
	public function execute($sql);
	
	/**
	 * Returns the last insert id from an insert command passed to execute() for the current connection.
	 * 
	 * TODO: This should be a separate interface. Also, rename to getLastSerial?
	 * 
	 * @return string|int
	 * Last insert id, or 0 if there is none.
	 */
	public function getLastInsertId(); 
	
	/**
	 * Encodes a value for use as an SQL literal. It has the following enhancements over the typical quote() method 
	 * found in PDO and other baseline layers:
	 * 
	 * - PHP null will convert to SQL NULL (unquoted).
	 * - Non-negative integers and strings which represent valid non-negative integers (unsigned 64-bit range) won't be
	 * quoted, so they can be used, for ex. with LIMIT and OFFSET. Note that a string with leading zeroes is not 
	 * considered a valid integer here, so if you pass a string of digits with leading zeroes, it'll be quoted as a
	 * string. This ensures caller data is not damaged if an integer-like string is set on a string column.
	 * 
	 * @param scalar|null $value
	 * A value to encode.
	 * 
	 * @return string
	 * Encoded value.
	 */
	public function encodeValue($value);
	
	/**
	 * Encodes a string as an SQL identifier. Ensures the identifier escapes illegal characters and doesn't collide with
	 * SQL keywords.
	 * 
	 * @param string $ident
	 * String identifier to encode.
	 * 
	 * @param bool $respectDots
	 * Optional (default = false). If false, dots in the identifier are treated as a part of the name (no special
	 * meaning). If true, dots will be interpreted as separators (so you can encode, say, table.column strings in one
	 * call).
	 * 
	 * @return string
	 * Encoded identifier.
	 */
	public function encodeIdent($ident, $respectDots = false);
	
	/**
	 * Begins an SQL transaction.
	 * 
	 * @param string $isolation
	 * Optional (default = null). An SQL-compatible string setting the isolation level of the transaction.
	 * 
	 * See the TI_* constants for available isolation levels. Implementation may add more TI_* constants depending on
	 * their needs.
	 * 
	 * If null, the isolation requested is assumed to match the default isolation level for this database type.
	 * 
	 * Callers shouldn't change the default isolation level for a connection at runtime through execute(), as this 
	 * represents runtime connection state that will persist between sessions, but the sessions aren't aware of. In
	 * general any connection-shared state should be avoided, sticking to block-specific & statement-specific settings.
	 * 
	 * @param string $fulfillment
	 * Optional (default = null, interpreted as self::TF_REAL). By default transactions can't be nested without native
	 * support by the RDBMS vendor. SqlSession supports emulation of nested transaction through two techniques, 
	 * savepoint transactions (self::TF_SAVEPOINT) and virtual transactions (self::TF_VIRTUAL). See the docblock 
	 * comments on the self::TF_* constants for more information.
	 * 
	 * @return mixed
	 * Returns a transaction id which you need to pass to commit() or rollback().
	 */
	public function begin($isolation = null, $fulfillment = null);
	
	/**
	 * Commits an SQL transaction started earlier with begin().
	 * 
	 * @param mixed $tid
	 * Transaction id returned by begin().
	 */
	public function commit($tid);
	
	/**
	 * Rolls back an SQL transaction started earlier with begin().
	 * 
	 * @param int $tid
	 * Transaction id returned by begin().
	 */
	public function rollback($tid);
	
	/**
	 * Calls the given closure in an SQL transaction. This is an alternative to the begin/commit/rollback methods.
	 * 
	 * Note: if you want to skip the optional parameters $isolation and $fulfillment you can directly pass $function as
	 * a first or second parameter, i.e. those calls are equivalent:
	 * 
	 * <code>
	 * $sqlSess->callInTransaction(null, null, function () { ... });
	 * $sqlSess->callInTransaction(null, function () { ... });
	 * $sqlSess->callInTransaction(function () { ... });
	 * </code>
	 * 
	 * Despite the $function argument is declared optional (for technical reasons), you must pass a function to this
	 * method.
	 * 
	 * @param string $isolation
	 * See begin().
	 * 
	 * @param string $fulfillment
	 * See begin().
	 * 
	 * @param \Closure $function
	 * Function to call within an SQL transaction. The transaction is committed as the function returns.
	 * 
	 * The following conditions result in transaction rollback instead of a commit:
	 * 
	 * - Returning boolean false from the function. Note, this doesn't apply for "falsey" values like null, 0 or empty
	 * string, but strictly boolean false.
	 * - Any uncaught exception escaping the function.
	 */
	public function transactional($isolation = null, $fulfillment = null, $function = null);
	
	/**
	 * Returns true, unless a nested virtual transaction (self::TF_VIRTUAL) have issued a rollback, so the parent
	 * transaction won't be allowed to commit.
	 * 
	 * The consistency flag is contextual. Once a parent transaction fulfilled via TF_SAVEPOINT or TF_REAL rolls back on
	 * a transaction stack flagged as inconsistent, the connection is marked consistent again. See also setConsistent().
	 * 
	 * @return bool
	 * True if there are no nested virtual transactions in the current transaction which have issued a rollback, false
	 * if there are. Transactions in an inconsistent state can't commit, only roll back. An attempt to commit results in
	 * a rollback and a throws exception.
	 *  
	 * If the session/connection is not in a transaction you also get true.
	 */
	public function isConsistent();
	
	/**
	 * Allows the caller to override the consistent flag for the current transaction stack. See isConsistent(). This
	 * method should be used very carefully, only if the caller is sure they can resolve the incosistency caused by a 
	 * TF_VIRTUAL transaction that has issued a rollback.
	 * 
	 * If you attempt to call this method while you're not in a transaction, an exception is thrown.
	 * 
	 * TODO: We need to port over the inTransaction() and other methods that give info on the current transaction state.
	 *  
	 * @param bool $consistent
	 */
	public function setConsistent($consistent);
}