<?php
namespace Solver\Shake;

/**
 * Implements a log that can only have error events in it, plus the ability to throw a ServiceException when there
 * are errors (see throwIfErrors()).
 * 
 * TODO: Refactor this as a separate generic error log class without the exception throwing part, so non-services don't
 * abuse this log for the purpose of holding and mapping events (via import()).
 * 
 * @author Stan Vass
 * @copyright © 2013-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class ServiceLog implements ErrorLog, EventProvider {
	use ImportEventsTrait;
	
	private $events = [];
	
	public function __construct() {}
	
	public function addError($path = null, $message = null, $code = null, $details = null) {
		$e = [];
		$e['type'] = 'error';
		$e['path'] = $path;
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