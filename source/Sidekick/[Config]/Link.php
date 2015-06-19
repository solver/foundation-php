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

abstract class Link {
	protected $first = null;
	protected $second = null;
	protected $junction = null;
	 
	function setFirstTable($tableName, $columnName, $fieldName = null) {
		$this->first = ['tableName' => $tableName, 'columnName' => $columnName, 'fieldName' => $fieldNamespace];
	}
	
	function setSecondTable($tableName, $columnName, $fieldName = null) {
		$this->second = ['tableName' => $tableName, 'columnName' => $columnName, 'fieldName' => $fieldNamespace];
	}
	
	function setJunctionTable($tableName) { // Required only for many-to-many; for the rest is optional and changes how the relation is computed.
		$this->junction = ['tableName' => $tableName];
	}
	
	function render() {
		if ($this->first === null) throw new \Exception('First table is a required property.');
		if ($this->second === null) throw new \Exception('Second table is a required property.');
		return get_object_vars($this);
	}
}