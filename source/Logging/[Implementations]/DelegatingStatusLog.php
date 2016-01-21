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

class DelegatingStatusLog implements StatusLog {
	// TODO: Possible optimization: implement shortcut methods locally, with inline mask checks & delegating to parent.
	use StatusLogConvenienceMethods;
	
	private $log;
	private $mask;
	private $filter;
	
	/**
	 * @param Log $log
	 * Log to send events to.
	 * 
	 * @param int $mask
	 * Optional. Event type mask to use for the log (if you pass null or nothing, StatusLog::T_DEFAULT is used).
	 * 
	 * @param \Closure $filter
	 * (list<dict>) => list<dict>; An optional filter which will receive events to be logged, and can return modified
	 * and filtered events to be logged instead (you can return an empty array to filter out all events if you need).
	 */
	public function __construct(Log $log, $mask = null, \Closure $filter = null) {
		$this->log = $log;
		$this->mask = $mask === null ? StatusLog::T_DEFAULT : $mask;
		$this->filter = $filter;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\StatusLog::getMask()
	 */
	public function getMask() {
		return $this->mask;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array ...$events) {
		static $map = [
			'error' => self::T_ERROR,
			'warning' => self::T_WARNING,
			'info' => self::T_INFO,
			'success' => self::T_SUCCESS,
		];
		
		$mask = $this->mask;
		$filter = $this->filter;
		
		// We need to be atomic, so we first check conditions, then send in one go.
		// If the mask allows all types, we can take a faster route without filtering.
		if ($mask == StatusLog::T_ALL) {
			foreach ($events as $event) {
				$type = $event['type'];
				if (!isset($map[$type])) LogException::throwUnknownType($type);
			}
			
			if ($filter) $events = $filter($events);
			$this->log->log(...$events);
		} else {
			$matchingEvents = [];
			foreach ($events as $event) {
				$type = $event['type'];
				if (isset($map[$type])) {
					if ($this->mask & $map[$type]) $matchingEvents[] = $event;
				} else {
					LogException::throwUnknownType($type);
				}
			}
			
			if ($filter) $matchingEvents = $filter($matchingEvents);
			$this->log->log(...$matchingEvents);
		}
		
	}
}