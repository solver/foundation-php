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
use PDOException;
use Solver\Insight\Debug;

abstract class PdoSession implements SqlSession {
	/**
	 * @var PdoSessionContext
	 */
	protected $context;
	protected $name;
	protected $refs;
	protected $refCount;
	
	/**
	 * Set on demand, if an open connection is needed. Else null.
	 * 
	 * @var PDO
	 */
	protected $handle = null;
	
	/**
	 * Stores the proper valid last insert id for MySQL. See getLastIdentity().
	 * 
	 * @var int
	 */
	protected $lastId = null;
	
	/**
	 * False if a TF_VIRTUAL transaction has issues a rollback which hasn't been materialized by a parent TF_REAL or
	 * TF_SAVEPOINT transaction.
	 * 
	 * @var bool
	 */
	protected $consistent = true;
	
	/**
	 * A stack of transactions (last index = inner-most transaction).
	 * 
	 * @var PdoMySqlConnectionTx[]
	 */
	protected $transactions = [];
	
	protected static $transactionId = 0;
	
	// Should be defined in the child class.
	protected static $transactionIsoLabelToIndex;
	
	/**
	 * DO NOT INSTANTIATE a session directly. Use the relevant SqlPool implementation to instantiate it, instead.
	 * 
	 * TODO: We don't ever use $name's value, we only check if it's null. Reduce to a boolean $named? We might need the
	 * name if we ever implement cleaning up instantiated handle-less named sessions.
	 */
	public function __construct($context, $name) {
		$this->context = $context;
		
		if ($name !== null) {
			// HEAVY WIZARDRY:
			// PHP references stay as references when there are 2+ variables pointing to the same reference. We need
			// clones to remain in sync, so we need them to refer to the same properties. So we need to ensure that at
			// all times there are at least 2 references to every property that might mutate or be re-assigned. So... we
			// just have this object for this purpose.
			// TODO: Test which is faster, this duplication or referring to the object directly throughout the class.
			// Thing is we probably don't want to drag down anon connections along (which don't need this object).
			$this->refs = $refs = new PdoSessionSharedState();
			$this->refCount = & $refs->refCount;
			$this->handle = & $refs->handle;
			$this->lastId = & $refs->lastId;
			$this->transactions = & $refs->transactions;
			$this->consistent = & $refs->consistent;
		}
	}
	
	public function __destruct() {
		$refCount = --$this->refCount;
		
		// Because named connections always have one instance in the pool, we release the handle when refCount is 1. For
		// anonymous connections we release when refCount is 0 (i.e. no more instances of that connection).
		if ($this->handle && $refCount == ($this->name === null ? 0 : 1)) {
			if ($this->transactions) throw new SqlException('An SQL session object was destroyed with one or more on-going transactions.');
			
			$this->context->drop->__invoke($this->handle);
			$this->handle = null;
		}
	}
	
	public function __clone() {
		++$this->refCount;		
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::open()
	 */
	public function open() {
		if ($this->handle) return;
		
		try {
			$this->handle = $this->context->take->__invoke();
		} catch (PDOException $e) {
			throw new SqlException($e->getMessage(), $e->getCode(), null, $e);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::close()
	 */
	public function close() {
		if ($this->transactions) throw new SqlException('A connection cannot be close with one or more on-going transactions.');
		
		if ($this->handle) {
			$this->context->drop->__invoke($this->handle);
			$this->handle = null;
		}
	}
	
	/**
	 * Runs a query, returns a statement.
	 * 
	 * @see \Solver\Sql\SqlSession::query()
	 * 
	 * @param string $sql
	 * SQL query string to execute.
	 * 
	 * @return PdoMysqlResultSet
	 */
	public function query($sql, $x = 'legacy params') {
		// TODO: Remove this temp helper for detecting problematic legacy code. Then remove $x.
		if ($x !== 'legacy params') throw new \Exception('Using legacy query(..., params). No longer supported.');
		
		if (!$this->handle) $this->open();
		
		if (Debug\LOG && $log = Debug::getLog(__METHOD__)) {
			$log->addInfo('SQL query: ' . $sql);
		}
		
		try {
			$handle = $this->handle->query($sql);
			$lastId = $this->handle->lastInsertId();
			
			if ($lastId !== null && $lastId != 0) {
				$this->lastId = $lastId;
			}
			
			$statement = new PdoResultSet($this, $handle);
		} catch (PDOException $e) {
			throw new SqlException($e->getMessage() . ' for query "' . $sql . '"', $e->getCode(), null, $e);
		}
		
		return $statement;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::execute()
	 */
	public function execute($sql, $x = 'legacy params') {
		// TODO: Remove this temp helper for detecting problematic legacy code. Then remove $x.
		if ($x !== 'legacy params') throw new \Exception('Using legacy execute(..., params). No longer supported.');
		
		if (!$this->handle) $this->open();
		
		if (Debug\LOG && $log = Debug::getLog(__METHOD__)) {
			$log->addInfo('SQL command: ' . $sql);
		}
		
		try {
			$affectedRows = $this->handle->exec($sql);
			$lastId = $this->handle->lastInsertId();
			if ($lastId !== null && $lastId != 0) {
				$this->lastId = $lastId;
			}
		} catch (PDOException $e) {
			throw new SqlException($e->getMessage() . ' for query "' . $sql . '"', $e->getCode(), null, $e);
		}
		
		return $affectedRows;
	}
		
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::getLastIdentity()
	 */
	public function getLastIdentity() {		
		if (!$this->handle) $this->open();
		
		// TODO: Obviously we're doing a bit of class detecting magic here that has to go. First we need to investigate
		// if we ever need the direct queries here? Does this code even run? When would the id not be set after a query
		// (aside from it was in another object, which is a case we don't support). Investigate, and if possible, remove
		// the whole thing.
		if ($this->lastId === null) {
			switch (get_class($this)) {
				case PdoSqliteSession::class:
					$result = $this->query('SELECT last_insert_rowid()')->getOne(0);
					break;
				case PdoMysqlSession::class:
					$result = $this->query('SELECT LAST_INSERT_ID()')->getOne(0);
					break;
				default:
					throw new \Exception('Unsupported sub-class.');
			}
			
			$this->lastId = $result;
		}

		return $this->lastId;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::encodeValue()
	 */
	public function encodeValue($value) {
		if (!$this->handle) $this->open();
		
		if ($value === null) return 'NULL';
		
		// We check the value of a large number using string comparison as PHP has no uint64 support.
		// Comparing number values as strings is valid as long as they have the same number of digits / chars.
		static $uint64Max = '18446744073709551615';
		static $uint64MaxLen = 20;
	
		if (is_int($value) && $value >= 0) return (string) $value;
		
		if ((is_float($value) && floor($value) === $value && $value >= 0) ||
			(is_string($value) && ctype_digit($value) && isset($value[0]) && $value[0] !== '0')) {
			
			$value = (string) $value;
			$len = strlen($value);
			
			if ($len < $uint64MaxLen || ($len == $uint64MaxLen && $value <= $uint64Max)) {
				return $value;
			}
		}
		
		// TODO: Check PDO options for native int encoding. We should ensure we don't cast string with leading zeroes
		// as int as this would damage user data (we must preserve the data semantically in any context).
		return $this->handle->quote($value);
	}	
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::begin()
	 */
	public function begin($isolation = null, $fulfillment = null) {
		if (!$this->handle) $this->open();
		
		$transactions = & $this->transactions;
		$transactionCount = count($transactions);
		$currentTransaction = $transactionCount ? $transactions[$transactionCount - 1] : null;
		$id = self::$transactionId++;
		
		/*
		 * Interpret isolation requirements.
		 */
		
		if ($isolation === null) {
			$isolationIndex = $this->context->isolationDefault;
		} else {
			$isoLabelToIndex = self::$transactionIsoLabelToIndex;
			
			if (!isset($isolabelToIndex[$isolation])) {
				throw new \Exception('Unknown isolation level "' . $isolation . '".');
			}
		
			$isolationIndex = $isoLabelToIndex[$isolation];
		}
		
		if ($currentTransaction && $isolationIndex > $currentTransaction->isolationIndex) {
			// We only need the flipped version here, when throwing an exception, so flipped on-demand is acceptable.
			$isoIndexToLabel = array_flip(self::$transactionIsoLabelToIndex);
			$currentIsoLevel = $isoIndexToLabel[$currentTransaction->isolationIndex];
			$newIsoLevel = $isoIndexToLabel[$isolationIndex];
			
			throw new SqlException('A nested transaction cannot have a higher isolation mode "' . $newIsoLevel . '" than its parent with isolation mode "' . $currentIsoLevel . '".');
		}
		
		/*
		 * Interpret fulfillment requirements.
		 */
	
		if (!$currentTransaction) {
			$fulfillment = self::TF_REAL;
		} else {
			if ($fulfillment === null) {
				$fulfillment = self::TF_REAL;
			}
			
			if ($fulfillment === self::TF_REAL) {
				throw new SqlException('Cannot fulfill real transaction as one is already in progress for this connection.');
			}
		}
		
		/*
		 * Begin.
		 */
		
		switch ($fulfillment) {
			case self::TF_REAL:
				if ($isolationIndex === $this->context->isolationDefault) {
					// TODO: We should debug-log native calls.
					$this->handle->beginTransaction();
				} else {
					$this->beginRealWithOptions($isolation);
				}
				break;
				
			case self::TF_SAVEPOINT:
				$this->execute('SAVEPOINT solver' . $id);
				break;
				
			case self::TF_VIRTUAL:
				// Nothing to do.
				break;
				
			default:
				throw new \Exception('Unknown fulfillment type "' . $fulfillment . '".');
		}
		
		/*
		 * Update connection state.
		 */
		
		$t = new PdoSessionTx();
		$t->id = $id;
		$t->isolationIndex = $isolationIndex;
		$t->fullfillment = $fulfillment;
		
		$this->transactions[] = $t;
		
		return $id;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::commit()
	 */
	public function commit($tid) {
		/* @var PdoSessionTx $t */
		$t = array_pop($this->transactions);
		
		if (!$t) throw new SqlException('There is no open transaction.');

		// TODO: Should we look up the stack to find a match & roll back for it?
		if ($tid !== $t->id) {
			throw new SqlException('The given transaction id doesn\'t match the last opened transaction.');
		}
		
		if (!$this->consistent && $t->fullfillment !== self::TF_VIRTUAL) {
			$this->rollback($tid);
			throw new SqlException('Cannot commit an inconsistent transaction. Rollback performed.');
		}
		
		switch ($t->fullfillment) {
			case self::TF_REAL:
				// TODO: We should debug-log native calls.
				if ($this->context->isolationDefault == $t->isolationIndex) {
					$this->handle->commit();
				} else {
					$this->execute('COMMIT');
				}
				break;
				
			case self::TF_SAVEPOINT:
				$this->execute('RELEASE SAVEPOINT solver' . $t->id);
				break;
				
			// Nothing to do for self::TF_VIRTUAL.
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::rollback()
	 */
	public function rollback($tid) {
		/* @var PdoMysqlconnectionTx $t */
		$t = array_pop($this->transactions);
		
		if (!$t) throw new SqlException('There is no open transaction.');
		
		// TODO: Should we look up the stack to find a match & roll back for it?
		if ($tid !== $t->id) {
			throw new SqlException('The given transaction id doesn\'t match the last opened transaction.');
		}
		
		switch ($t->fullfillment) {
			case self::TF_REAL:
				// TODO: We should debug-log native calls.
				if ($this->context->isolationDefault == $t->isolationIndex) {
					$this->handle->rollBack();
				} else {
					$this->execute('ROLLBACK');
				}
				// Rolling back before an inconsistency caused by a virtual tx restores consistency.
				$this->consistent = true;
				break;
				
			case self::TF_SAVEPOINT:
				$this->execute('ROLLBACK TO solver' . $t->id);
				// Restoring to a savepoint before an inconsistency caused by a virtual tx restores consistency.
				$this->consistent = true;
				break;
				
			case self::TF_VIRTUAL:
				// Virtual rollback marks the transaction inconsistent and doesn't do anything on the db.
				$this->consistent = false;
				break;
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::transactional()
	 */
	public function transactional($isolation = null, $fulfillment = null, $function = null) {
		// A bit of parameter remapping...
		switch (\func_num_args()) {
			case 1: 
				$function = $isolation;
				$isolation = null;
				break;
			case 2:
				$function = $fulfillment;
				$fulfillment = null;
				break;
		}
		
		if (!$function instanceof \Closure) {
			throw new \Exception('Parameter $function must be a Closure instance.');
		}
		
		$tid = $this->begin($isolation, $fulfillment);
				
		try {
			$result = $function();
			if ($result === false) {
				$this->rollback($tid);
			} else {
				$this->commit($tid);
			}
		} catch (\Exception $e) {
			$this->rollback($tid);
			throw $e;
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::isConsistent()
	 */
	public function isConsistent() {
		return $this->consistent;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::setConsistent()
	 */
	public function setConsistent($consistent) {
		$this->consistent = $consistent;
	}
	
	/**
	 * The child class should begin a real transaction with the given explicit isolation level string.
	 * 
	 * @param string $isolation
	 */
	abstract protected function beginRealWithOptions($isolation);
}

/**
 * IMPORTANT: This class is an internal implementation detail. Will change or go away without warning.
 * TODO: Make this an anon class in PHP7.
 */
class PdoSessionSharedState {
	public $refCount = 1, $handle = null, $lastId = null, $transactions = [], $consistent = true;
}

/**
 * IMPORTANT: This class is an internal implementation detail. Will change or go away without warning.
 * TODO: Make this an anon class in PHP7.
 */
class PdoSessionTx {
	public $id, $isolationIndex, $fullfillment;
}