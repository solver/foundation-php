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

trait OrderTrait {
	protected $orderFields = null;
	
	function order($fields) {
		// Passing ["foo"] is a shortcut for ["foo" => "ASC"].
		foreach ($fields as $k => $v) {
			if (is_int($k)) {
				unset($fields[$k]);
				$fields[$v] = "ASC";
			}
		}
		
		foreach ($fields as $k => $v) {
			if ($v !== 'ASC' && $v !== 'DESC') throw new \Exception('Invalid sort order value "' . $v . '", should be "ASC" or "DESC".');
		}
				
		if ($this->orderFields) {
			$this->orderFields = array_merge($this->orderFields, $fields);
		} else {
			$this->orderFields = $fields;
		}
		
		return $this;
	}
}