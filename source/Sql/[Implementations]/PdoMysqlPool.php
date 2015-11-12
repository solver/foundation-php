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

class PdoMysqlPool extends PdoPool {
	protected $config;
	
	/**
	 * @param array $config
	 * dict...
	 * - host: string;
	 * - port: int;
	 * - user: string;
	 * - password: string;
	 * - database: string;
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
		return new PdoMysqlSession($this->context, null);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::newNamedSession()
	 */
	protected function newNamedSession($name) {
		return new PdoMysqlSession($this->context, $name);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlPool::newNamedSession()
	 */
	protected function newConnection() {
		$config = $this->config;
		
		// TODO: Once we require MySQL 5.5.3+, we should set the connection by default to utf8mb4, see:
		// http://dev.mysql.com/doc/refman/5.5/en/charset-unicode-utf8mb4.html (test if it works fine with both
		// latin1, utf8 and utf8mb4 columns and tables).
		
		// TODO: We should expose the charset parameter in $config.
		return new \PDO('mysql:host=' . $config['host'].
			(isset($config['port'])?':' . $config['port']:'').
			';dbname=' . $config['database'] . ';charset=utf8',
			$config['user'], 
			$config['password']
		);
	}
}