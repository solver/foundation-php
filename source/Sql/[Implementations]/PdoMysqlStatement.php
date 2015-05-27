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

use PDO;
use PDOStatement;

/**
 * This is a forward compatible subset of the full Solver\Lab\SqlStatement class, supports only MySQL.
 * 
 * Contains and executes a single SQL query, returns results.
 * 
 * TODO: Fix PDO default buffering not to be used for fetchNext, but used for fetchAll.
 */
class PdoMysqlStatement implements SqlStatement {
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
			$row = $this->handle->fetch(PDO::FETCH_ASSOC);
			
			if ($row) {
				$index = null; 
				
				foreach ($row as $k => $v) {
					if ($k == $field) {
						break;
					}
					
					$index++;
				}
				
				if ($index === null) throw new SqlException('Trying to fetch non-existent result set column "' . $field . '".');
			} else {
				return [];
			}
			
			return \array_merge(array($row[$field]), $this->handle->fetchAll(PDO::FETCH_COLUMN, $index));
		}	
	}
	
	protected function errorClosed() {
		throw new SqlException('This statement is closed.');
	}
}