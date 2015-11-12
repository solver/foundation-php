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

class PdoMysqlSession extends PdoSession {
	protected static $transactionIsoLabelToIndex = [
		'READ UNCOMMITTED' => 1,
		'READ COMMITTED' => 2,
		'REPEATABLE READ' => 3,
		'SERIALIZABLE' => 4,
	];
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\SqlSession::encodeIdent()
	 */
	public function encodeIdent($identifier, $respectDots = false) {
		return $respectDots 
			? '`' . \str_replace(['`', '.'], ['``', '`.`'], $identifier) . '`'
			: '`' . \str_replace('`', '``', $identifier) . '`';
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sql\PdoSession::beginReal()
	 */
	protected function beginRealWithOptions($isolation) {
		// TODO: MySQL supports many more modifiers here: http://dev.mysql.com/doc/refman/5.7/en/commit.html
		// We might need to refactor begin/commit into a dedicated transaction object to capture the extra features
		// of every RDBMS vendor.
		$this->execute('SET TRANSACTION ISOLATION LEVEL ' . $isolation);
		$this->execute('BEGIN');
	}
}