<?php
namespace Solver\Lab;

/**
 * Logs messages, warnings, errors at the controller level, which are passed to the view for display.
 * 
 * Every controller has an automatically designated ControllerLog as member $log.
 * 
 * When the log fails itself due to bad API usage or fatal error, it will throw a generic Exception, not a
 * ControllerException.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class ControllerLog implements EventProvider, ErrorLog {
	use ImportEventsTrait;
	
	const TYPE_SUCCESS = 'success';
	const TYPE_INFO = 'info';
	const TYPE_WARNING = 'warning';
	const TYPE_ERROR = 'error';
		
	/**
	 * Contains the full collection of messages logged, linearly one after another as they were added.
	 */
	protected $events = array();
	
	/**
	 * Same as events, but indexed by path (each path key is a list of the events having that path).
	 */
	protected $eventsByPath = array();
		
	/**
	 * Counts error messages (enables getErrorCount() and hasErrors()).
	 */
	protected $errorCount = 0;
	
	public function __construct() {}
	
	/**
	 * Positive message, typically confirming success after an action. Please specify at least one of $message or $code.
	 * 
	 * @param string $path
	 * Path location for this event. Optional (default = null).
	 * 
	 * @param string $message
	 * Event description for human reading. Optional (default = null).
	 * 
	 * @param string $code
	 * String code for machine reading. Optional (default = null).
	 * 
	 * @param array $details
	 * Hashmap with message context & information vars (freeform). Optional (default = null).
	 */
	public function addSuccess($path = null, $message = null, $code = null, $details = null) {
		$e = [];
		$e['path'] = $path;
		$e['type'] = self::TYPE_SUCCESS;
		$e['message'] = $message;
		$e['code'] = $code;
		$e['details'] = $details;
		$this->addEvent($e);
	}	
	
	/**
	 * Neutral information. Please specify at least one of $message or $code.
	 * 
	 * @param string $path
	 * Path location for this event. Optional (default = null).
	 * 
	 * @param string $message
	 * Event description for human reading. Optional (default = null).
	 * 
	 * @param string $code
	 * String code for machine reading. Optional (default = null).
	 * 
	 * @param array $details
	 * Hashmap with message context & information vars (freeform). Optional (default = null).
	 */
	public function addInfo($path = null, $message = null, $code = null, $details = null) {
		$e = [];
		$e['path'] = $path;
		$e['type'] = self::TYPE_INFO;
		$e['message'] = $message;
		$e['code'] = $code;
		$e['details'] = $details;
		$this->addEvent($e);
	}	
	
	/**
	 * Non-fatal informational error condition, milder than error. Please specify at least one of $message or $code.
	 * 
	 * @param string $path
	 * Path location for this event. Optional (default = null).
	 * 
	 * @param string $message
	 * Event description for human reading. Optional (default = null).
	 * 
	 * @param string $code
	 * String code for machine reading. Optional (default = null).
	 * 
	 * @param array $details
	 * Hashmap with message context & information vars (freeform). Optional (default = null).
	 */
	public function addWarning($path = null, $message = null, $code = null, $details = null) {
		$e = [];
		$e['path'] = $path;
		$e['type'] = self::TYPE_WARNING;
		$e['message'] = $message;
		$e['code'] = $code;
		$e['details'] = $details;
		$this->addEvent($e);
	}	
	
	/**
	 * Fatal error condition. Please specify at least one of $message or $code.
	 * 
	 * @param string $path
	 * Path location for this event. Optional (default = null).
	 * 
	 * @param string $message
	 * Event description for human reading. Optional (default = null).
	 * 
	 * @param string $code
	 * String code for machine reading. Optional (default = null).
	 * 
	 * @param array $details
	 * Hashmap with message context & information vars (freeform). Optional (default = null).
	 */
	public function addError($path = null, $message = null, $code = null, $details = null) {
		$e = [];
		$e['path'] = $path;
		$e['type'] = self::TYPE_ERROR;
		$e['message'] = $message;
		$e['code'] = $code;
		$e['details'] = $details;
		$this->addEvent($e);
	}
	
	/**
	 * Adds an event dict to the log.
	 * 
	 * @param array $event
	 * Required keys: type, message, code, details, path.
	 */
	protected function addEvent(array $event) {		
		$this->events[] = $event;	
		
		$path = $event['path'];
		if ($path === null) $path = '(main)';
		
		if (!isset($this->eventsByPath[$event['path']])) $this->eventsByPath[$event['path']] = [];
		$this->eventsByPath[$event['path']][] = $event;
		
		if ($event['type'] === self::TYPE_ERROR) $this->errorCount++;
	}
	
	/**
	 * Returns a hashmap with the full list of log messages grouped by path.
	 * 
	 * Items with no path (path null) appear under key '(main)' (parens can't be in a valid path hence no collisions).
	 * 
	 * <code>
	 * [$path][][message/code/path/context/type] A full list of events for the given path, in order of logging.
	 * </code>
	 * 
	 * @param string $path
	 * Optional (default = null, all paths). By passing a string you'll get a list of events having a specific path.
	 * Otherwise you'll get a dict of [path => [list of events], ...] for all paths in the log. 
	 * 
	 * @return array
	 */
	public function getAllEventsByPath($path = null) {
		if ($path === null) return $this->eventsByPath;
		if (isset($this->eventsByPath[$path])) return $this->eventsByPath[$path];
		return [];
	}
		
	/**
	 * Returns an array with the full list of log events, requires that the log is closed.
	 * 
	 * @return array
	 */
	public function getAllEvents() {
		return $this->events;
	}
		
	/**
	 * Returns true/false depending on whether the log has any errors.
	 * 
	 * @return bool
	 */
	public function hasErrors() {	
		return (bool) $this->errorCount;
	}	
		
	/**
	 * Returns the number of errors in the log.
	 * 
	 * @return int
	 */
	public function getErrorCount() {	
		return $this->errorCount;
	}	
			
	protected function errorClosed() {
		throw new \Exception('Can not perform actions on a closed log.');
	}
		
	protected function errorNotClosed() {
		throw new \Exception('Can not perform dump on an open log, close first.');
	}
}