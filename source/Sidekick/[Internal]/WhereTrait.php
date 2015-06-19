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

trait WhereTrait {
	protected $whereFields = null;
	
	function where($fields) {
		foreach ($fields as $k => $v) {
			if (!$v instanceof Expr && !is_scalar($v)) {
				throw new \Exception('Values in a where clause should be set to a value or Expr instance.');
			}
		}
		
		if ($this->whereFields) {
			// TODO: Detect collisions and throw on them.
			$this->whereFields += $fields;
		} else {
			// TODO: Detect non-scalar values here, or elsewhere.
			// TODO: Allow also Expression instances for values, or instead of the whole array (later).
			$this->whereFields = $fields;
		}
		
		return $this;
	}
}