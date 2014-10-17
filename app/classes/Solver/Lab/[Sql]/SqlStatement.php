<?php
namespace Solver\Shake;

use PDO;
use PDOStatement;

/**
 * This is a forward compatible subset of the full Solver\Shake\SqlStatement class, supports only MySQL.
 * 
 * Contains and executes a single SQL query, returns results.
 * 
 * TODO: Fix PDO default buffering not to be used for fetchNext, but used for fetchAll.
 * 
 * @author Stan Vass
 * @copyright © 2008-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class SqlStatement {
	/**
	 * @var \PDOStatement
	 */
	protected $handle;
	
	/**
	 * Results were fetched from the query and it was closed.
	 * 
	 * @var bool
	 */
	protected $closed = false;
	
	/**
	 * @var string
	 */
	protected $query;
	
	/**
	 * To be called by the relevant SqlConnection class (do not instantiate directly).
	 * 
	 * @param \PDOStatement $handle
	 */
	public function __construct(PDOStatement $handle) {
		$this->handle = $handle;
	}
	
	/**
	 * Fetches one row and closes the statement.
	 * 
	 * @param int|string $field
	 * Optional (default = null). Column index (int) or name (string). If set, only this column is returned. Otherwise,
	 * a dict of all fields specified in the query is returned.
	 * 
	 * @return mixed
	 * Dict of result fields (names => values), or if a field name was specified, the field alone. 
	 * 
	 * Null is returned if there's no result to fetch.
	 */
	public function fetchOne($field = null) {		
		if ($this->closed) $this->errorClosed();
		
		if (\is_int($field)) {
			$row = $this->handle->fetch(PDO::FETCH_COLUMN, $field);
			$this->handle->closeCursor();
			$this->closed = true;
			return $row === false ? null : $row;
		} else {
			$row = $this->handle->fetch(PDO::FETCH_ASSOC);
			$this->handle->closeCursor();
			$this->closed = true;
			
			if ($field === null) {
				return $row === false ? null : $row;
			} else {
				return isset($row[$field]) ? $row[$field] : null;
			}
		}
	}
		
	/**
	 * Fetches all rows and closes the statement.
	 * 
	 * @param int|string $field
	 * Numeric index (int) or name (string). If set, only a list of the values of this column is returned. Otherwise, 
	 * the each entry in the list is a dict of all fields specified in the query is returned.
	 * 
	 * @return mixed
	 * A list of dicts for each result row (field name => value), or a list of field values, if specified.
	 * 
	 * An empty array is returned if there are no results to fetch.
	 */	
	public function fetchAll($field = null) {		
		if ($this->closed) $this->errorClosed();
		
		$this->closed = true;
		
		if ($field === null) {
			return $this->handle->fetchAll(PDO::FETCH_ASSOC);
		} else if (\is_int($field)) {
			return $this->handle->fetchAll(PDO::FETCH_COLUMN, $field);
		} else if (\is_string($field)) {						
			if (isset($this->fieldMap[$field])) {
				return $this->handle->fetchAll(PDO::FETCH_COLUMN, $this->fieldMap[$field]);
			} else {
				$row = $this->handle->fetch(PDO::FETCH_ASSOC);
				
				if ($row) {
					$p = 0; foreach ($row as $k=>$v) {
						if ($k == $field) break; $p++;
					}
					$this->fieldMap[$field] = $p;
				} else {
					return [];
				}
				
				
				return \array_merge(array($row[$field]), $this->handle->fetchAll(PDO::FETCH_COLUMN, $this->fieldMap[$field]));
			}
		}	
	}
	
	protected function errorClosed() {
		throw new SqlConnectionException('This statement is closed.');
	}
}