<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
namespace Solver\Lab;

use PDO;
use PDOStatement;
use PDOException;

/**
 * This is a forward compatible subset of the full Solver\Lab\SqlConnection class, supports MySQL and SQLite for now.
 */
class SqlConnection {
	/**
	 * Database type ("sqlite" or "mysql").
	 * 
	 * @var string
	 */
	var $type;
	
	/**
	 * @var array
	 */
	protected $config;
	
	/**
	 * @var boolean
	 */
	protected $open;
	
	/**
	 * @var PDO
	 */
	protected $handle;
	
	/**
	 * Stores the proper valid last insert id for MySQL. See getLastId().
	 * 
	 * @var int
	 */
	protected $lastId = null;
	
	/**
	 * @param array $config
	 * Supported $config keys:
	 * 
	 * For MySQL:
	 * 
	 * - String "host", required.
	 * - Int "port", optional.
	 * - String "user", required.
	 * - String "password", required.
	 * - String "database", required.
	 * 
	 * For SQLite:
	 * - String "file", required. Use ":memory:" to create a temp database, or an absolute filepath to an SQLite db.
	 */
	public function __construct($config) {
		$this->config = $config;
	}
	
	public function open() {
		if ($this->open) return false;
		
		try {
			$config = $this->config;
			
			// SQLite.
			if (isset($config['file'])) {
				$this->handle = new PDO('sqlite:' . $config['file']);
				
				$this->transIsoLevels = $this->transIsoLevelsSqlite;
				
				$this->type = 'sqlite';
			} 
			
			// MySQL.
			else {
				// TODO: Once we require MySQL 5.5.3+, we should set the connection by default to utf8mb4, see:
				// http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html (test if it works fine with both
				// latin1, utf8 and utf8mb4 columns and tables).
				
				// TODO: We should expose the charset parameter in $config.
				$this->handle = new PDO('mysql:host=' . $config['host'].
					(isset($config['port'])?':' . $config['port']:'').
					';dbname=' . $config['database'] . ';charset=utf8', 
					$config['user'], 
					$config['password']
				);
				
				$this->transIsoLevels = $this->transIsoLevelsStandard;
				
				$this->type = 'mysql';
			}
			
			$this->handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			
			$this->open = true;
		} catch (PDOException $e) {
			throw new SqlConnectionException($e->getMessage(), $e->getCode(), $e);
		}
	}
	
	protected $transIsoLevels;
	
	protected $transIsoLevelsSqlite = [
		'DEFERRED' => 1, 
		'IMMEDIATE' => 2, 
		'EXCLUSIVE' => 3,
	];
	
	protected $transIsoLevelsStandard = [
		'READ UNCOMMITTED' => 1,
		'READ COMMITTED' => 2,
		'REPEATABLE READ' => 3,
		'SERIALIZABLE' => 4,
	];
	
	/**
	 * Runs a query, returns a statement.
	 * 
	 * @param string $sql
	 * SQL query string to execute.
	 * 
	 * @param array $values
	 * Optional (default = null). A list of values that will be "quoted into" the $query in place of question marks (see
	 * quoteInto).
	 * 
	 * @return \Solver\Lab\SqlStatement
	 */
	public function query($sql, array $values = null) {		
		if (!$this->open) $this->open();
		
		if (\func_num_args() > 1) $sql = $this->quoteInto($sql, $values);	
		
		try {
			$handle = $this->handle->query($sql);
			$lastId = $this->handle->lastInsertId();
			
			if ($lastId !== null && $lastId != 0) {
				$this->lastId = $lastId;
			}
			
			$statement = new SqlStatement($handle, $this);
		} catch (PDOException $e) {
			throw new SqlConnectionException($e->getMessage(), $e->getCode(), $e);
		}
		
		return $statement;
	}
	
	public function execute($sql, $values = null) {		
		if (!$this->open) $this->open();
		
		if (\func_num_args() > 1) $sql = $this->quoteInto($sql, $values);
		
		try {
			$affectedRows = $this->handle->exec($sql);
			$lastId = $this->handle->lastInsertId();
			if ($lastId !== null && $lastId != 0) {
				$this->lastId = $lastId;
			}
		} catch (PDOException $e) {
			throw new SqlConnectionException($e->getMessage(), $e->getCode(), $e);
		}
		
		return $affectedRows;
	}
		
	public function getLastId() {		
		if (!$this->open) $this->open();
		
		if ($this->lastId === null) {
			if ($this->type === 'sqlite') {
				$result = $this->query('SELECT last_insert_rowid()')->fetchOne(0);
			} else {
				$result = $this->query('SELECT LAST_INSERT_ID()')->fetchOne(0);
			}
			
			$this->lastId = $result;
		}

		return $this->lastId;
	}
	
	/**
	 * Quotes a value for use as an SQL literal. It has the following enhancements over the typical quote() method in
	 * other layers:
	 * 
	 * - PHP null will convert to SQL NULL.
	 * - Non-negative integers and strings which represent valid non-negative integers (unsigned 64-bit range) won't be
	 * quoted, so they can be used, for ex. with LIMIT and OFFSET. Note that a string with leading zeroes is not 
	 * considered a valid integer in this context.
	 * - Passing an array will return the array with every value in it quoted.
	 * 
	 * @param array|string|float|int|null $value
	 * A value, or an array of values to quote. The function works recursively, so if you have, say, an array of strings
	 * inside the array you pass, it'll all get quoted.
	 * 
	 * @return string
	 * Quoted value.
	 */
	public function quoteValue($value) {
		if (!$this->open) $this->open();
		
		if (!\is_array($value)) {
			if ($value === null) return 'NULL';
			
			// We check the value of a large number using string comparison as PHP has no uint64 support.
			// Comparing number values as strings is valid as long as they have the same number of digits / chars.
			static $uint64Max = '18446744073709551615';
			static $uint64MaxLen = 20;
		
			if (is_int($value) && $value >= 0) return (string) $value;
			
			if ((is_float($value) && floor($value) === $value && $value >= 0) ||
				(is_string($value) && ctype_digit($value) && $value[0] !== '0')) {
				
				$value = (string) $value;
				$len = strlen($value);
				
				if ($len < $uint64MaxLen || ($len == $uint64MaxLen && $value <= $uint64Max)) {
					return $value;
				}
			}
			
			return $this->handle->quote($value);
		} else {			
			foreach ($value as & $v) {
				$v = $this->quoteValue($v);
			}
			unset($v);
			
			return $value;
		}
	}
	
	/**
	 * Replaces question marks in the $sql string with quoted SQL value literals.
	 * 
	 * Note this isn't a prepared statement
	 * API, the engine just looks for question marks without analyzing the query structure (i.e. if your $sql string
	 * contains a question mark within a string literal, it'll get replaced as well).
	 * 
	 * @param unknown $sql
	 * SQL string containing one more question marks "?".
	 * 
	 * @param unknown $values
	 * A list of values to replace in the SQL string. 
	 * 
	 * @return string
	 */
	public function quoteInto($sql, array $values) {		
		$values = $this->quoteValue($values);
		$sql = \explode('?', $sql);
		$ce = \count($sql);
		
		if ($ce != \count($values) + 1) {
			throw new SqlConnectionException('The number of values passed does not match the number of replace marks in the expression.');
		}
		
		$out = \reset($sql) . \reset($values) . \next($sql);
		
		for ($i = 2; $i < $ce; $i++) {
			$out .= \next($values).$sql[$i];
		}	

		return $out;
	}
	
	/**
	 * Quotes a string (or an array of strings) as SQL identifiers. This is only required for untrusted identifier, and
	 * identifiers containing illegal identifier characters (say, a dot).
	 * 
	 * @param array|string $identifier
	 * A value, or a list of values to quote. The function works recursively, so if you have, say, an array of strings
	 * inside the array you pass, it'll all get quoted.
	 * 
	 * @param bool $respectDots
	 * Optional (default = false). If false, dots in the identifier are treated as a part of the name (no special
	 * meaning). If true, dots will be interpreted as separators (so you can quote say table.column strings in one
	 * call).
	 * 
	 * @return string
	 * Quoted value.
	 */
	public function quoteIdentifier($identifier, $respectDots = false) {
		// TODO: Quoting identifiers doesn't need an open connection, but it needs the $this->type, so for now, as a workaround we open it.		
		if (!$this->open) $this->open();
		
		if (!\is_array($identifier)) {
			switch ($this->type) {
				case 'mysql':
					return $respectDots 
					? '`' . \str_replace(['`', '.'], ['``', '`.`'], $identifier) . '`'
					: '`' . \str_replace('`', '``', $identifier) . '`';
					break;
				case 'sqlite':
// 				case 'pgsql':
					return $respectDots 
					? '"' . \str_replace(['"', '.'], ['""', '"."'], $identifier) . '"'
					: '"' . \str_replace('"', '""', $identifier) . '"';
					break;
// 				case 'mssql':	
// 					return $respectDots 
// 					? '"' . \str_replace(['"', '.'], ['""', '"."'], $identifier) . '"'
// 					: '"' . \str_replace('"', '""', $identifier) . '"';
// 					break;
				default:
					throw new \Exception('Server not supported.');
			}
		} else {		
			foreach ($identifier as $i) {
				$i = $this->quoteIdentifier($i);
			}
			
			return $identifier;
		}
		

	}
	
	/**
	 * Quotes every key in the dict as an identifier, and every value as an SQL value literal, i.e. [ident => value].
	 * 
	 * @param array $row
	 * A dict where the keys will be treated as SQL identifiers, and the values will be treated as SQL value literals.
	 * 
	 * @param bool $respectDots
	 * See quoteIdentifier() for details.
	 * 
	 * @return array
	 * Quoted dict.
	 */
	public function quoteRow(array $row, $respectDots = false) {
		return \array_combine($this->quoteIdentifier(\array_keys($row), $respectDots), $this->quoteValue(\array_values($row)));
	}
	
	/**
	 * Calls the given closure in an SQL transaction.
	 * 
	 * Note: if you want to skip the optional parameter $isolation, you can directly pass $function as a first
	 * parameter, i.e. those two calls are equivalent:
	 * 
	 * <code>
	 * $conn->callInTransaction(null, function () { ... });
	 * $conn->callInTransaction(function () { ... });
	 * </code>
	 * 
	 * @param string $isolation
	 * Optional (default = null, which is treated as "REPEATABLE READ"). An SQL-compatible string setting the isolation
	 * level of the transaction.
	 * 
	 * @param \Closure $function
	 * Function to call within an SQL transaction. These condition will result in transaction rollback instead of a 
	 * commit:
	 * 
	 * - Returning boolean false from the function. Note, this doesn't apply for "falsey" values like null, 0 or empty
	 * string, but strictly boolean false.
	 * - Any uncaught exception leaving the function.
	 */
	public function transactional($isolation = null, $function = null) {
		// A bit of parameter remapping...
		if (\func_num_args() == 1) {
			$function = $isolation;
			$isolation = null;
		}
		
		if (!($function instanceof \Closure)) {
			throw new \Exception('Parameter $function must be a Closure instance.');
		}
		
		if ($isolation === null) $isolation = 'REPEATABLE READ';
		
		if (!isset($this->transIsoLevels[$isolation])) {
			throw new \Exception('Unknown isolation level "' . $isolation . '".');
		}
		
		if ($this->type === 'sqlite') { // Pgsql also supports this, for when I add it.
			$this->exec('BEGIN ' . $isolation);
		} else {
			$this->execute('SET TRANSACTION ISOLATION LEVEL ' . $isolation);
			$this->execute('BEGIN');
		}
				
		try {
			$result = $function();
			if ($result === false) {
				$this->execute('ROLLBACK');
			} else {
				$this->execute('COMMIT');
			}
		} catch (\Exception $e) {
			$this->execute('ROLLBACK');
			throw $e;
		}
	}
	

	/**
	 * Inserts a single row.
	 * 
	 * @param string $table
	 * Table name (will be quoted as identifier).
	 * 
	 * @param array $row
	 * Hashmap representing row (automatically quoted).
	 */
	public function insert($table, array $row) {
		$this->insertMany($table, array($row));
	}
	

	/**
	 * Inserts a list of new rows into a table.
	 * 
	 * @param string $table
	 * Table name (will be quoted as identifier).
	 * 
	 * @param array $rows
	 * A list of rows (automatically quoted). Each row is a dict formatted [colName => value, colName => value, ...].
	 * 
	 * @param bool $extended
	 * Optional (default = false). If true, the engine will attempt to insert all passed rows in a single extended
	 * insert query. This requires that all rows to be inserted have the same columns.
	 */
	public function insertMany($table, array $rows, $extended = false) {
		if (empty($rows)) return;
		
		$tblQ = $this->quoteIdentifier($table);
		
		if (!$extended) {		
			// TODO: Wrap in a transaction if count($rows) > 1 (once we have nested transactions again).	
			for($i = 0, $max = \count($rows); $i < $max; $i++) {				
				$row = $this->quoteRow($rows[$i]);	
				$sql = 'INSERT INTO ' . $tblQ . ' (' . \implode(',', \array_keys($row)).') VALUES (' . \implode(',', $row) . ')';
				$this->execute($sql);
			}
		}
		
		// Single extended insert (cols specified for each row should match).
		else {
			$cols = \array_keys($rows[0]);
			$colsQ = $this->quoteIdentifier($cols);
			
			// When imploded, forms the VALUES part of the query.
			$valSeq = array();
			
			for($i = 0, $max = \count($rows); $i < $max; $i++) {
				$row = $this->quoteValue($rows[$i]);
				
				$vals = array();
				
				// TRICKY: we're looping the column names from the first row, and retrieving the values in that order,
				// as even if all rows have the same columns specified, they may not necessarily be written in the same
				// order in the PHP array.
				foreach($cols as $col) {			
					if (\array_key_exists($col, $row)) {
						$vals[] = $row[$col];
					} else {
						throw new SqlConnectionException('Column "' . $col . '" expected but not found in row number ' . $i . '.');
					}
				}
				
				$valSeq[] = '(' . \implode(',', $vals) . ')';
			}
			
			$sql = 'INSERT INTO '.$tblQ.' (' . implode(',', $colsQ) . ') VALUES ' . implode(',', $valSeq);

			$this->execute($sql);
		}
	}
		
	/**
	 * Updates a table row, matching it by the column(s) specified as a primary key.
	 * 
	 * @param string $table
	 * Table name (will be quoted as identifier).
	 * 
	 * @param string|array $pkCols
	 * String column name of the primary key (or a list of column names for composite keys).
	 * 
	 * @param array $row
	 * A dict representing a row. The columns used as PK must be present, but it's not a requirement to pass all of the
	 * remaining cols in the table, only those to be updated.
	 */
	public function updateByPrimaryKey($table, $pkCols, array $row) {	
		return $this->updateByPrimaryKeyMany($table, $pkCols, array($row));
	}	
	
	/**
	 * Executes updates for all passed rows, matching them by the column(s) specified as id (primary key).
	 * 
	 * @param string $table
	 * Table name (will be quoted as identifier).
	 * 
	 * @param string $pkCols
	 * String column name of the primary key (or array of column names for composite keys).
	 * 
	 * @param array $rows
	 * Array of hashmaps representing a row each. The columns used as id must be specified, but it's not a requirement
	 * to have all other columns.
	 */
	public function updateByPrimaryKeyMany($table, $pkCols, $rows) {
		if (empty($rows)) return;
		
		$tblQ = $this->quoteIdentifier($table);
		$pkColsQ = $this->quoteIdentifier($pkCols);
		
		if (\is_array($pkCols)) {
			foreach ($rows as $rk => $row) {
				$row = $this->quoteValue($row);
				
				$setArr = array();
				$whereArr = array();
				
				// TRICKY: above we escaped the id colnames in $pkColsQ so we need the keys (idk) to fetch them from the actual id-s.
				foreach ($pkCols as $pkCol => $pkVal) {
					if (!isset($row[$pkVal])) throw new SqlConnectionException('Primary key column "' . $pkVal . '" missing in an update row at index "' . $rk . '".');
					$whereArr[] = $pkColsQ[$pkCol].' = '.$row[$pkVal];
					unset($row[$pkVal]);
				}
				
				foreach ($row as $rk => $rv) {
					$setArr[] = $this->quoteIdentifier($rk).' = '.$rv;
				}
				
				if (!$setArr) return;
				
				$q = 'UPDATE '.$tblQ.' SET '.$setArr(',', $setArr).' WHERE '.implode(',', $whereArr);
				
				$this->execute($q);
			}
		} else {
			foreach ($rows as $rk => $row) {
				$row = $this->quoteValue($row);
				
				$setArr = array();
				
				if (!isset($row[$pkCols])) throw new SqlConnectionException('Identifier "' . $pkCols . '" missing in an update row at index "' . $rk . '".');
				$where = $pkColsQ . ' = ' . $row[$pkCols];
				unset($row[$pkCols]);
				
				foreach ($row as $rk => $rv) {
					$setArr[] = $this->quoteIdentifier($rk) . ' = ' . $rv;
				}
				
				if (!$setArr) return;
				
				$q = 'UPDATE ' . $tblQ . ' SET ' . implode(',', $setArr) . ' WHERE ' . $where;
				
				$this->execute($q);
			}			
		}
	}	
	
	/**
	 * Blanks the table. Optionally will reset the autoIncrement counter.
	 * 
	 * @param string $table
	 * Table name (will be quoted as identifier).
	 * 
	 * @param bool $resetAutoIncrement
	 * Whether to reset autoincrement to 1.
	 */
	public function truncate($table, $resetAutoIncrement = false) {
		$tblQ = $this->quoteIdentifier($table);
		
		$this->execute('TRUNCATE TABLE ' . $tblQ);
		
		if ($resetAutoIncrement) {
			$this->execute('ALTER TABLE ' . $tblQ . ' AUTO_INCREMENT = 1');
		}
	}
}