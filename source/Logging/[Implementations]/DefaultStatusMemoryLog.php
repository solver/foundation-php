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
namespace Solver\Logging;

// TODO: Optimization opportunities.
class DefaultStatusMemoryLog extends DelegatingStatusLog implements StatusMemoryLog {
	/**
	 * We re-declare this parent property to assign it a proper type (we know it's this type as we configure the 
	 * parent constructor with it).
	 * 
	 * @var DefaultMemoryLog
	 */
	protected $log;
	
	public function __construct($mask = 15) {
		parent::__construct(new DefaultMemoryLog(), $mask);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::getErrors()
	 */
	public function getErrors() {
		return $this->log->getEvents(['error']);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::hasErrors()
	 */
	public function hasErrors() {
		return $this->log->hasEvents(['error']);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::getEvents()
	 */
	public function getEvents($types = null) {
		return $this->log->getEvents($types);
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::hasEvents()
	 */
	public function hasEvents($types = null) {
		return $this->log->hasEvents($types);
	}
}