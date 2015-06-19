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
use PDOException;

// Has bits for other databases, extract into abstract base class.
class PdoMySqlConnection implements SqlConnection {
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
	 * Stores the proper valid last insert id for MySQL. See getLastInsertId().
	 * 
	 * @var int
	 */
	protected $lastId = null;
	
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
	 * @param array $config
	 * dict...
	 * - host: string;
	 * - port: int;
	 * - user: string;
	 * - password: string;
	 * - database: string;
	 * 
	 * For SQLite, instead we have:
	 * TODO: Obviously we need to split off the sqlite logic off of the "PdoMysql" class...
	 * dict...
	 * - file: string; Use ":memory:" to create a temp database, or an absolute filepath to an SQLite db.
	 */
	public function __construct($config) {
		$this->config = $config;
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::open()
	 */
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
			throw new SqlException($e->getMessage(), $e->getCode(), null, $e);
		}
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::close()
	 */
	public function close() {
		throw new \Exception('TODO'); // TODO
	}
	
	/**
	 * Runs a query, returns a statement.
	 * 
	 * @see \Solver\Sql\SqlConnection::query()
	 * 
	 * @param string $sql
	 * SQL query string to execute.
	 * 
	 * @return PdoMysqlResultSet
	 */
	public function query($sql) {
		var_dump($sql);
		if (!$this->open) $this->open();
		
		try {
			$handle = $this->handle->query($sql);
			$lastId = $this->handle->lastInsertId();
			
			if ($lastId !== null && $lastId != 0) {
				$this->lastId = $lastId;
			}
			
			$statement = new PdoMysqlResultSet($handle, $this);
		} catch (PDOException $e) {
			throw new SqlException($e->getMessage(), $e->getCode(), null, $e);
		}
		
		return $statement;
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::execute()
	 */
	public function execute($sql) {		
		var_dump($sql);
		if (!$this->open) $this->open();
		
		try {
			$affectedRows = $this->handle->exec($sql);
			$lastId = $this->handle->lastInsertId();
			if ($lastId !== null && $lastId != 0) {
				$this->lastId = $lastId;
			}
		} catch (PDOException $e) {
			throw new SqlException($e->getMessage(), $e->getCode(), null, $e);
		}
		
		return $affectedRows;
	}
		
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::getLastInsertId()
	 */
	public function getLastInsertId() {		
		if (!$this->open) $this->open();
		
		if ($this->lastId === null) {
			if ($this->type === 'sqlite') {
				$result = $this->query('SELECT last_insert_rowid()')->getOne(0);
			} else {
				$result = $this->query('SELECT LAST_INSERT_ID()')->getOne(0);
			}
			
			$this->lastId = $result;
		}

		return $this->lastId;
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::encodeValue()
	 */
	public function encodeValue($value) {
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
				$v = $this->encodeValue($v);
			}
			unset($v);
			
			return $value;
		}
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::encodeIdent()
	 */
	public function encodeIdent($identifier, $respectDots = false) {
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
// 					return $respectDots 2*2
// 					? '"' . \str_replace(['"', '.'], ['""', '"."'], $identifier) . '"'
// 					: '"' . \str_replace('"', '""', $identifier) . '"';
// 					break;
				default:
					throw new \Exception('Server not supported.');
			}
		} else {		
			foreach ($identifier as $i) {
				$i = $this->encodeIdent($i);
			}
			
			return $identifier;
		}
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::encodeRow()
	 */
	public function encodeRow(array $row, $respectDots = false) {
		return \array_combine($this->encodeIdent(\array_keys($row), $respectDots), $this->encodeValue(\array_values($row)));
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Sql\SqlConnection::transactional()
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
			$this->execute('BEGIN ' . $isolation);
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
}