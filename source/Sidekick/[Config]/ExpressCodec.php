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

use Solver\Sidekick\SqlContext as SC;

/**
 * Invokes all on*() handlers that match the context. 
 * 
 * Every call to on*() adds a new handler (or handlers), it doesn't replace the previous handlers. The handlers are
 * guaranteed to be invoked in the order they were added.
 */
class ExpressCodec implements Codec {
	protected $mask = 0, $fields, $onClause = [], $onResult = [];

	/**
	 * @param string[]|null $fields
	 * The value to be returned by getFields(). Can be empty for global handlers.
	 */
	public function __construct($fields = null) {
		$this->fields = $fields;
	}
	
	public function onInsert(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_INSERT_STATEMENT | SC::C_ALL_STATEMENTS, $encodeClauseHandlers);
		return $this;
	}
	
	public function onQuery(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_INSERT_STATEMENT | SC::C_ALL_STATEMENTS, $encodeClauseHandlers);
		return $this;
	}
	
	public function onUpdate(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_UPDATE_STATEMENT | SC::C_ALL_STATEMENTS, $encodeClauseHandlers);
		return $this;
	}
	
	public function onDelete(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_DELETE_STATEMENT | SC::C_ALL_STATEMENTS, $encodeClauseHandlers);
		return $this;
	}
	
	public function onInsertValues(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_INSERT_STATEMENT | SC::C_VALUES, $encodeClauseHandlers);
		return $this;
	}
	
	public function onQuerySelect(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_SELECT, $encodeClauseHandlers);
		return $this;
	}
	
	public function onQueryFrom(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_FROM, $encodeClauseHandlers);	
		return $this;	
	}
	
	public function onQueryJoin(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_JOIN, $encodeClauseHandlers);	
		return $this;
	}
	
	public function onQueryWhere(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_WHERE, $encodeClauseHandlers);
		return $this;
	}
	
	public function onQueryGroup(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_GROUP, $encodeClauseHandlers);
		return $this;
	}
	
	public function onQueryHaving(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_HAVING, $encodeClauseHandlers);
		return $this;
	}
	
	public function onQueryOrder(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_QUERY_STATEMENT | SC::C_ORDER, $encodeClauseHandlers);	
		return $this;
	}
		
	public function onUpdateFrom(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_UPDATE_STATEMENT | SC::C_FROM, $encodeClauseHandlers);
		return $this;
	}
		
	public function onUpdateJoin(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_UPDATE_STATEMENT | SC::C_JOIN, $encodeClauseHandlers);
		return $this;
	}
	
	public function onUpdateSet(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_UPDATE_STATEMENT | SC::C_SET, $encodeClauseHandlers);
		return $this;
	}
	
	public function onUpdateWhere(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_UPDATE_STATEMENT | SC::C_WHERE, $encodeClauseHandlers);
		return $this;
	}
	
	public function onUpdateOrder(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_UPDATE_STATEMENT | SC::C_ORDER, $encodeClauseHandlers);
		return $this;
	}
	
	public function onDeleteFrom(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_DELETE_STATEMENT | SC::C_FROM, $encodeClauseHandlers);
		return $this;
	}
	
	public function onDeleteJoin(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_DELETE_STATEMENT | SC::C_JOIN, $encodeClauseHandlers);
		return $this;
	}
	
	public function onDeleteWhere(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_DELETE_STATEMENT | SC::C_WHERE, $encodeClauseHandlers);
		return $this;
	}
	
	public function onDeleteOrder(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_DELETE_STATEMENT | SC::C_ORDER, $encodeClauseHandlers);
		return $this;
	}
	
	public function onSelect(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_SELECT, $encodeClauseHandlers);
		return $this;
	}
	
	public function onFrom(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_FROM, $encodeClauseHandlers);
		return $this;
	}
	
	public function onJoin(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_JOIN, $encodeClauseHandlers);
		return $this;
	}
	
	public function onWhere(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_WHERE, $encodeClauseHandlers);
		return $this;
	}
	
	public function onGroup(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_GROUP, $encodeClauseHandlers);
		return $this;
	}
	
	public function onHaving(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_HAVING, $encodeClauseHandlers);
		return $this;
	}
	
	public function onOrder(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_ORDER, $encodeClauseHandlers);
		return $this;
	}
	
	public function onValues(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_VALUES, $encodeClauseHandlers);
		return $this;
	}
	
	public function onSet(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL_STATEMENTS | SC::C_SET, $encodeClauseHandlers);
		return $this;
	}
	
	public function onAnyClause(\Closure ...$encodeClauseHandlers) {
		$this->addClauseHandlers(SC::C_ALL, $encodeClauseHandlers);
		return $this;
	}
	
	public function onResult(\Closure ...$decodeRowsHandlers) {
		array_merge($this->onResult, $decodeRowsHandlers);
		return $this;
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
		if ($this->onClause) {
			$mask = $sqlContext->getMask();
			
			// TODO: We run O(N) scan here on every call and this should be optimized, but we must do it in a way that
			// both preserves handler call order as defined (as promised) and doesn't cause excessive index generation
			// on on*() calls. Possibly materialize index for a specific combo on first call for a mask type. It should
			// be benchmarked on real-world use cases.
			foreach ($this->onClause as list($handlerMask, $handler)) {
				if (($handlerMask & $mask) === $mask) $handler->__invoke($sqlContext, $fieldsIn, $columnsOut);
			}
		}
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Codec::decodeRows()
	 */
	public function decodeRows($selectedFields, $rowsIn, & $recordsOut) {
		if ($this->onResult) foreach ($this->onResult as $handler) {
			$handler->__invoke($selectedFields, $rowsIn, $recordsOut);
		}
	}
	
	protected function addClauseHandlers($mask, $callbacks) {
		$this->mask |= $mask;
		
		foreach ($callbacks as $callback) {
			$this->onClause[] = [$mask, $callback];
		}
	}
}