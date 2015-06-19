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

// TODO: Add HAVING clause, GROUP clause, and primitive outer/left/right/inner JOIN (by matching two fields: including expression fields).
// TODO: Maybe add FORCE and IGNORE INDEX options (maybe no need to have the indexes on the schema as it'll be caught at the server anyway).
class QueryStatement extends AbstractStatement {
	use WhereTrait;
	use OrderTrait;
	
	protected $forUpdate = false;
	protected $forShare = false;
	protected $distinct = false;
	protected $distinctRow = false;
	
	protected $selectFields = null;
	
	protected $limit = 0;
	protected $offset = 0;
	
	public function __construct(\Closure $finalize) {
		$this->finalize = $finalize;
	}
	
	function forUpdate($enable = true) {
		if ($enable) {
			$this->forShare = false;
			$this->forUpdate = true;
		} else {
			$this->forUpdate = false;
		}
		
		return $this;
	}
	
	function forShare($enable = true) {
		if ($enable) {
			$this->forUpdate = false;
			$this->forShare = true;
		} else {
			$this->forShare = false;
		}
		
		return $this;
	}
	
	function distinct($enable = true) {
		$this->distinct = true;
		
		return $this;
	}
	
	function distinctRow($enable = true) {
		$this->distinctRow = true;
		
		return $this;
	}
	
	function select($fields) {
		// Passing ["foo"] is a shortcut for ["foo" => null].
		foreach ($fields as $k => $v) {
			if (is_int($k)) {
				unset($fields[$k]);
				$fields[$v] = null;
			}	
		}
		
		if ($this->selectFields) {
			// TODO: Detect collisions and throw on them.
			$this->selectFields += $fields;
		} else {
			// TODO: Detect non-scalar values here, or elsewhere.
			$this->selectFields = $fields;
		}
		
		return $this;
	}

	function range($limit, $offset = 0) {
		$this->limit = 0;
		$this->offset = 0;
		
		return $this;
	}
	
	function getOne($col = null) {
		return $this->finalize->__invoke($this->render(), false, $col);
	}
	
	function getAll($col = null) {
		return $this->finalize->__invoke($this->render(), true, $col);
	}
}