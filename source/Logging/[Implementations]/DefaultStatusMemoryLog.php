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
	private $log;
	private $retainsErrors;
	private $lastError = null;
	
	public function __construct($mask = StatusLog::T_DEFAULT) {
		$log = new DefaultMemoryLog();
		$this->log = $log;
		$this->retainsErrors = (bool) ($mask & StatusLog::T_ERROR); 
		parent::__construct($log, $mask);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::getLastError()
	 */
	public function getLastError() {
		return $this->lastError;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::getErrors()
	 */
	public function getErrors() {
		// TODO: If method getErrors() is called often it might be good to materialize this list, than filter it on 
		// every call. The way we do with getLastError().
		$events = $this->log->getEvents();
		$errors = [];
		foreach ($events as $event) if ($event['type'] === 'error') $errors[] = $event;
		return $errors;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\ErrorMemoryLog::hasErrors()
	 */
	public function hasErrors() {
		return (bool) $this->lastError !== null;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::getEvents()
	 */
	public function getEvents() {
		return $this->log->getEvents();
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::hasEvents()
	 */
	public function hasEvents() {
		return $this->log->hasEvents();
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\DelegatingStatusLog::log()
	 */
	public function log(array ...$events) {
		if ($this->retainsErrors) for ($i = count($events) - 1; $i >= 0; $i--) {
			if ($events[$i]['type'] === 'error') {
				$this->lastError = $events[$i];
				break;
			}
		}
		
		parent::log(...$events);
	}
}