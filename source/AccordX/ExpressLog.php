<?php
namespace Solver\AccordX;

use Solver\Logging\StatusLogConvenienceMethods;
use Solver\Logging\Log;
use Solver\Logging\StatusLog;
use Solver\Logging\DefaultStatusMemoryLog;
use Solver\Logging\DelegatingStatusLog;
use Solver\Accord\ActionException;
use Solver\Logging\LogUtils;

/**
 * This is an implementation a StatusMemoryLog, which provides a number of features out of the box to make it a 
 * practical choice for higher-level actions, where micro-performance is less important.
 * 
 * For example you can use this log for web MVC actions, endpoint API actions, but you might want to avoid the overhead
 * if you are implementing a lower-level data validation/transform action (a light Transform or Format, for example).
 * 
 * Key properties of ExpressLog:
 * 
 * - The log can optionally copy all events to a parent log, so actions can quickly wrap a caller-provided log and 
 * work with the full ExpressLog toolset & without having to manage the caller-provided log separately.
 * - Provides convenient shortcuts throw*(). Method throwIfHasErrors() throws ActionException containing the log, if it
 * contains errors. Method throwError() adds an error and throws an ActionException. In all cases, if a parent log is
 * provided, the exception will point to this log, instead.
 * - The log is never masked (all status event types are logged), even if the parent log is masked. This means you can
 * rely that all messages (including errors) will be present in the log when passing it to sub-actions, and makes the
 * use of the provided throw*() methods reliable (you don't need to keep track of sub-action errors separately).
 * - Provides at() and filtered() that return write-only views into the log, which transform the logged events in 
 * commonly needed ways (the views are intended to be passed to sub-actions that process, for ex. a part of the action's
 * input, so they need to log at a specific event path).
 */
class ExpressLog extends DefaultStatusMemoryLog {
	use StatusLogConvenienceMethods;
	
	private $log = null; 
	
	/**
	 * @param Log $log
	 * Optional parent log any logged events will be logged, in addition. If a parent log is provided, this will be
	 * the log attached to any thrown ActionException (see throw*() methods).
	 */
	public function __construct(StatusLog $log = null) {
		// TODO: We can fetch the parent mask to save on some calls.
		$this->log = $log;
		
		// Should we reduce flags if parent log has lesser flags?
		parent::__construct(StatusLog::DEFAULT_MASK);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array ...$events) {
		$log = $this->log;
		
		// TODO: Not strictly atomic as superclass may throw (say, unknown event type) after we log at $this->log.
		// Make atomic by validating at superclass, then logging at $this->log, then committing events to superclass.
		if ($log) $log->log(...$events);
		parent::log(...$events);
	}
	
	public function throwIfHasErrors() {
		if ($this->hasErrors()) throw new ActionException($this->log ?: $this);
	}
	
	public function throwError(array $path = null, $message = null, $code = null, array $details = null) {
		$this->error($path, $message, $code, $details);
		throw new ActionException($this->log ?: $this);
	}
		
	/**
	 * Returns a write-only view of the current log, such that any events logged onto the view will show up in the
	 * current log with a path starting with the given path.
	 * 
	 * @param array $path
	 * Path prefix to use for all events.
	 * 
	 * @return StatusLog
	 * A write-only view into the current log (all events logged on the view will show up in the parent log).
	 */
	public function at(array $path) {
		if ($path) {
			return new DelegatingStatusLog($this, StatusLog::DEFAULT_MASK, function ($events) use ($path) {
				foreach ($events as & $event) {
					if (isset($event['path'])) {
						$event['path'] = array_merge($path, $event['path']);
					} else {
						$event['path'] = $path;
					}
				}
				
				return $events;
			});
		} else {
			return new DelegatingStatusLog($this, StatusLog::DEFAULT_MASK);
		}
	}
	
	/**
	 * TODO: Document.
	 * 
	 * @param \Closure $filter
	 * -
	 * 
	 * @return StatusLog
	 * A write-only view into the current log (all events logged on the view will show up in the parent log).
	 */
	public function filtered(\Closure $filter) {
		return new DelegatingStatusLog($this, StatusLog::DEFAULT_MASK, $filter);
	}
	
	/**
	 * TODO: Document.
	 * 
	 * See LogUtils::getPathMapFilter() for the map filter used.
	 * 
	 * @param \Closure $map
	 * -
	 * 
	 * @return StatusLog
	 * A write-only view into the current log (all events logged on the view will show up in the parent log).
	 */
	public function mapped(array $map) {
		return new DelegatingStatusLog($this, StatusLog::DEFAULT_MASK, LogUtils::getPathMapFilter($map));
	}
}