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
namespace Solver\Sidekick;

class AnonCodec implements Codec {
	protected $mask, $fields, $encodeClause, $decodeRows;
	
	/**
	 * @param int $mask
	 * The value to be returned by getMask().
	 * 
	 * @param string[]|null $fields
	 * The value to be returned by getFields(). Can be null for global handlers.
	 * 
	 * @param \Closure|null $encodeClause
	 * Closure implementing encodeClause(), or null if you don't want to define this method. If Sidekick invokes this
	 * method and you have passed null, AnonCodec silently does nothing.
	 * 
	 * @param \Closure|null $decodeRows
	 * Closure implementing decodeRows(), or null if you don't want to define this method. If Sidekick invokes this
	 * method and you have passed null, AnonCodec silently does nothing.
	 */
	public function __construct($mask, $fields, $encodeClause, $decodeRows) {
		$this->mask = $mask;
		$this->fields = $fields;
		$this->encodeClause = $encodeClause;
		$this->decodeRows = $decodeRows;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Codec::getMask()
	 */
	public function getMask() {
		return $this->mask;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Codec::getFields()
	 */
	public function getFields() {
		return $this->fields;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Codec::encodeClause()
	 */
	public function encodeClause(SqlContext $sqlContext, $fieldsIn, & $columnsOut) {
		if ($this->encodeClause) {
			return $this->encodeClause->__invoke($sqlContext, $fieldsIn, $columnsOut);
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Codec::decodeRows()
	 */
	public function decodeRows($selectedFields, $rowsIn, & $recordsOut) {
		if ($this->decodeRows) {
			return $this->decodeRows->__invoke($selectedFields, $rowsIn, $recordsOut);
		}
	}
}