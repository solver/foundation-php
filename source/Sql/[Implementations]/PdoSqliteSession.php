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

class PdoSqliteSession extends PdoSession {
	protected static $transactionIsoLabelToIndex = [
		'DEFERRED' => 1, 
		'IMMEDIATE' => 2, 
		'EXCLUSIVE' => 3,
	];
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::getServerType()
	 */
	public function getServerType() {
		return 'sqlite';
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::encodeName()
	 */
	public function encodeName($name, $respectDots = false) {
		return $respectDots 
			? '"' . \str_replace(['"', '.'], ['""', '"."'], $name) . '"'
			: '"' . \str_replace('"', '""', $name) . '"';
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\PdoSession::beginReal()
	 */
	protected function beginRealWithOptions($isolation) {
		// TODO: Sqlite also exposes READ UNCOMMITED (default SERIALIZABLE) so we might need to refactor the core 
		// interface to allow extra options to be set on a transaction object. The session will be left with a single
		// newTransaction() method. Alternatively: keep it like this and pass in a TransactionParams object to begin().
		
		// Pgsql also supports this syntax, for when I add it.
		$this->execute('BEGIN ' . $isolation);
	}
}