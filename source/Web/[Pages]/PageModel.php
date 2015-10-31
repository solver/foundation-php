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
namespace Solver\Web;

use Solver\Logging\StatusMemoryLog;
use Solver\Logging\StatusLog;
use Solver\Logging\DefaultStatusMemoryLog;

/**
 * Provides an abstract model of the page data & events, which is fed into the view (typically a template to render as
 * HTML).
 * 
 * The data is specific to each page (no general conventions, except: prefer arrays + scalars over objects).
 * 
 * The model is also a valid StatusMemoryLog implementation (with default event type mask), so you can pass it to
 * actions to log events directly to it. Some methods are extended to support filtering events by path.
 * 
 * All event-related method which accept path accept it both as an array and as a dot delimited string.
 */
class PageModel extends DataBox implements StatusMemoryLog {
	protected $log;
	
	public function __construct($fields = null, $events = null) {
		$log = new DefaultStatusMemoryLog();
		if ($events) $log->log(...$events);
		$this->log = $log;
		
		parent::__construct($fields);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\FromValue::fromValue()
	 * 
	 * @param array $value
	 * dict...
	 * - output: dict;
	 * - events: list<dict>;
	 * 
	 * @return $this
	 */	
	public static function fromValue($value, StatusLog $log = null) {
		// FIXME: Doesn't respect $this->path;
		new static(isset($value['output']) ? $value['output'] : null, isset($value['events']) ? $value['events'] : null);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\ToValue::toValue()
	 * 
	 * @return array
	 * dict...
	 * - output: dict;
	 * - events: list<dict>;
	 */
	public function toValue() {
		// FIXME: Doesn't respect $this->path;
		return [
			'output' => $this->fields,
			'events' => $this->log->getEvents(),
		];
	}
	
	public function at($path) {
		$sub = parent::at($path);
		$sub->log = $this->log;
		return $sub;
	}

	public static function getMask() {
		return StatusLog::DEFAULT_MASK;
	}
	
	public static function log(array ...$events) {
		if ($this->path) {
			$this->log->log(...$this->globalizeEvents($events));
		} else {
			$this->log->log(...$events);
		}
	}
	
	public static function hasEvents($path = null) {
		// TODO: Optimization opportunities.
		$path = $this->realPath($path);
		
		if ($path) {
			return (bool) $this->localizeEvents($this->log->getEvents(), $path);
		} else {
			return $this->log->hasEvents();
		}
	}
	
	public static function getEvents($path = null) {
		$path = $this->realPath($path);
		
		if ($path) {
			return $this->localizeEvents($this->log->getEvents(), $path);
		} else {
			return $this->log->getEvents();
		}}
	
	public static function hasErrors($path = null) {
		// TODO: Optimization opportunities.
		$path = $this->realPath($path);
		
		if ($path) {
			return (bool) $this->localizeEvents($this->log->getErrors(), $path);
		} else {
			return $this->log->hasErrors();
		}
	}
	
	public static function getErrors($path = null) {
		$path = $this->realPath($path);
		
		if ($path) {
			return $this->localizeEvents($this->log->getErrors(), $path);
		} else {
			return $this->log->getErrors();
		}
	}	
	
	public static function getLastError($path = null) {
		// TODO: Optimization opportunities.
		$errors = $this->getErrors($path);
		
		if ($errors) return array_pop($errors);
		else return null;
	}	
	
	public static function addError($path = null, $message = null, $code = null, array $details = null) {
		$path = $this->realPath($path);
		$this->log->addError($path, $message, $code, $details);
	}
	
	public static function addWarning($path = null, $message = null, $code = null, array $details = null) {
		$path = $this->realPath($path);
		$this->log->addWarning($path, $message, $code, $details);
		
	}
	
	public static function addInfo($path = null, $message = null, $code = null, array $details = null) {
		$path = $this->realPath($path);
		$this->log->addInfo($path, $message, $code, $details);
		
	}
	
	public static function addSuccess($path = null, $message = null, $code = null, array $details = null) {
		$path = $this->realPath($path);
		$this->log->addSuccess($path, $message, $code, $details);
	}
	
	/**
	 * Returns events with the $this->path prefixed to their path.
	 */
	protected function globalizeEvents($events) {
		if ($this->path) {
			$path = $this->path;
			foreach ($events as & $event) {
				if (isset($event['path'])) $event['path'] = $path;
			}
			return $events;
		} else {
			return $events;
		}
	}
	
	/**
	 * Returns events that start with $this->path (combined with $path, if supplied) and that prefix is stripped
	 * from their paths (a "local" view). 
	 */
	protected function localizeEvents($events, $path = null) {
		// TODO: Optimize with specific code for 1..8 path segments.
		if ($this->path) {
			$filteredEvents = [];			
			$basePath = $this->path;
			$baseCount = count($basePath);
			
			// We're comparing strictly to avoid weird string-to-number comparison semantics in PHP, so we cast 
			// everything to string.
			foreach ($basePath as & $seg) $seg = (string) $seg;
			unset($seg);
			
			
			foreach ($events as $event) {
				if (!isset($event['path'])) continue;
				if (count($event['path']) < $baseCount) continue;
				
				$path = & $event['path'];
				for ($i = 0; $i < $baseCount; $i++) if ($basePath[$i] !== (string) $path[$i]) continue 2;
				
				$path = array_slice($path, $baseCount);
				unset($path);
				
				$filteredEvents[] = $event;
			}
			
			return $filteredEvents;
		} else {
			return $events;
		}
	}
}