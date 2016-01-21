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

class QueryStatement extends AbstractStatement {
	use QueryTrait;
	
	protected $finalize;
	
	public function __construct(\Closure $finalize) {
		$this->finalize = $finalize;
	}
	
	function getOne($fieldOrIndex = null) {
		return $this->finalize->__invoke($this->render(), false, $fieldOrIndex);
	}
	
	function getAll($fieldOrIndex = null) {
		return $this->finalize->__invoke($this->render(), true, $fieldOrIndex);
	}
}