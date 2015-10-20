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
	private $has = []; // TODO: Can be replaced with a bitmask.
	private $events = [];
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array $event, array ...$events) {
		$type = $event['type'];
		if (!isset($this->has[$type])) $this->has[$type] = true;
		
		if ($events) {
			foreach ($events as $event) {
				$type = $event['type'];
				if (!isset($this->has[$type])) $this->has[$type] = true;
			}
			
			array_push($this->events, $event, ...$events);
		} else {
			$this->events[] = $event;
		}
	}	
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::getEvents()
	 */
	public function getEvents($types = null) {
		if ($types === null || !$this->events) {
			return $this->events;
		} else {
			$types = array_flip($types);
			
			// Special optimized case: the given $types mask matches or is a superset of the event types we have.
			$optimized = true;
			
			foreach ($this->has as $key => $val) if (!isset($types[$key])) {
				$optimized = false;
				break;
			}
				 
			if ($optimized) {
				return $this->events;
			} else {
				$events = [];
				foreach ($this->events as $event) if (isset($types[$event['type']])) $events[] = $event;
				return $events;
			}
		}
	}

	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\MemoryLog::hasEvents()
	 */
	public function hasEvents($types = null) {
		if ($types === null) {
			return (bool) $this->has;
		} else {
			foreach ($types as $type) if (isset($this->has[$type])) return true;
			return false;
		}
	}
}