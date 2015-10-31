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

class DefaultMemoryLog implements MemoryLog {
	private $events = [];
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array ...$events) {
		if ($events) {
			if (isset($events[1])) array_push($this->events, ...$events);
			else $this->events[] = $events[0];
		}
	}	
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::getEvents()
	 */
	public function getEvents() {
		return $this->events;
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::hasEvents()
	 */
	public function hasEvents() {
		return (bool) $this->events;
	}
}