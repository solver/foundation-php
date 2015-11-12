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

abstract class PdoPool implements SqlPool {
	protected $context;
	protected $pool = []; // A list<$handle>, as a stack of free connection handles for reuse.
	protected $named = []; // A dict<$name, $session> for shared named connections.
	
	protected function newContext() {
		// TODO: Debug-log in the take/drop function when we create a new connection, when it's returned to the pool,
		// lent from the pool.
		
		$this->context = $context = new PdoSessionContext();
		
		// On-demand obtaining of a connection handle.
		$context->take = function () {
			if ($this->pool) {
				return array_pop($this->pool);
			} else {
				try {
					$handle = $this->newConnection();
					$handle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				} catch (\PDOException $e) {
					throw new SqlException($e->getMessage(), $e->getCode(), null, $e);
				}
				
				return $handle;
			}
		};
		
		// Returning a connection handle to the pool.
		$context->drop = function ($handle) {
			$this->pool[] = $handle;
		};
		
		return $context;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::getSession()
	 */
	public function getSession($name) {
		if (isset($this->named[$name])) {
			return clone $this->named[$name]; // Clones have linked state (we clone to enable refcounting only).
		} else {
			$sqlSess = $this->newNamedSession($name);
			$this->named[$name] = $sqlSess;
			return clone $sqlSess;
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::flush()
	 */
	public function flush() {
		$this->pool = [];
	}
	
	abstract protected function newConnection();
	abstract protected function newNamedSession($name);
}

/**
 * IMPORTANT: This class is an internal implementation detail. Will change or go away without warning.
 * TODO: Make this an anon class in PHP7.
 */
class PdoSessionContext {
	public $take;
	public $drop;
	public $isolationDefault;
}