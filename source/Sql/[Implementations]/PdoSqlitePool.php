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

class PdoSqlitePool extends PdoPool {
	protected $config;
	
	/**
	 * @param array $config
	 * dict...
	 * - file: string; Use ":memory:" to create a temp database, or an absolute filepath to an SQLite db.
	 */
	public function __construct($config) {
		// TODO: Assert config format.
		$this->config = $config;
		
		$this->context = $context = $this->newContext();
		
		// TODO: Isolation default should be configurable. For now we stick to DB defaults.
		$context->isolationDefault = 1;		
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::newSession()
	 */
	public function newSession() {
		return new PdoSqliteSession($this->context, null);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::newNamedSession()
	 */
	protected function newNamedSession($name) {
		return new PdoSqliteSession($this->context, $name);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::newNamedSession()
	 */
	protected function newConnection() {
		return new \PDO('sqlite:' . $this->config['file']);
	}
}