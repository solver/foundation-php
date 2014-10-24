<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
namespace Solver\Lab;

/**
 * Implements a log that can only have error events in it, plus the ability to throw a ServiceException when there
 * are errors (see throwIfErrors()).
 * 
 * TODO: Refactor this as a separate generic error log class without the exception throwing part, so non-services don't
 * abuse this log for the purpose of holding and mapping events (via import()).
 */
class ServiceLog implements ErrorLog, EventProvider {
	use ImportEventsTrait;
	
	private $events = [];
	
	public function __construct() {}
	
	public function addError($path = null, $message = null, $code = null, $details = null) {
		$e = [];
		$e['type'] = 'error';
		// Null and empty string both mean "no path", but null is the canonical value for it.
		$e['path'] = $path === '' ? null : $path;
		$e['message'] = $message;
		$e['code'] = $code;
		$e['details'] = $details;
		$this->events[] = $e;
	}
	
	public function hasErrors() {
		return (bool) $this->events;
	}
	
	public function getErrorCount() {
		return \count($this->events);
	}
	
	public function throwIfErrors() {
		if ($this->events) {
			throw new ServiceException($this);
		}
	}
		
	public function getAllEvents() {
		return $this->events;
	}
}