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
namespace Solver\Accord;

use Solver\Logging\StatusLog;

class AnonAction implements Action {	
	/**
	 * @var \Closure
	 */
	protected $apply;
	
	public function __construct(\Closure $apply = null) {
		$this->apply = $apply;
	}
	
	public function setApplyMethod(\Closure $apply) {
		$this->apply = $apply;		
		return $this;
	}
	
	public function apply($input = null, StatusLog $log = null) {
		if ($this->apply) {
			return $this->apply->__invoke($input, $log);
		} else {
			// Developer mistake in configuring the action, hence we throw root Exception.
			throw new \Exception('Method apply() has not been set.');
		}
	}
}