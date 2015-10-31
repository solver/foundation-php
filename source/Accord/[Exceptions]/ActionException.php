<?php
namespace Solver\Accord;

use Solver\Logging\StatusLog;
use Solver\Logging\StatusMemoryLog;
use Solver\Logging\LogUtils;

/**
 * This exception is thrown from Action::apply() on failure.
 * 
 * It contains the log of the action that failed, if the action was given a log (or it had an internal log). 
 * 
 * IMPORTANT: It's best to use the included log only for debugging and diagnostics. There's no guarantee the log
 * contains properly mapped events for the action that failed, or that the included events all pertain to the action
 * that failed (it may contain previous messages if the caller gave an action a non-empty log). Instead, when the caller
 * needs to know what exactly happened, they should directly inspect the log *they* gave to an action.
 * 
 * TODO: Implement string message from last 16 messages (or so).
 */
final class ActionException extends \Exception {
	private $log;
	
	/**
	 * @param \Solver\Logging\StatusLog $log
	 * Status log of the failing action. Optional.
	 * 
	 * @param \Exception $previous
	 * Previous exception. Optional.
	 */
	public function __construct(StatusLog $log = null, \Exception $previous = null) {
		if ($previous) {
			parent::__construct(null, null, $previous);
		} else {
			parent::__construct();
		}
		
		$this->log = $log;
	}
	
	/**
	 * @return \Solver\Logging\StatusLog|null
	 * The status log of the failed action (if any). This method may return null. Please do heed the warning about using
	 * the log provided at the head doc comment in this class ("IMPORTANT: It's best to use the included log...").
	 */
	public function getLog() {
		return $this->log;
	}
	
	/**
	 * Provides a string summary of the last up to 16 errors, starting with the last one, if the exception contains a 
	 * readable log.
	 * 
	 * This method is also used when the exception is cast to a string (see __toString()).
	 * 
	 * @param int $maxErrorCount
	 * Optional (default = 16). Maximum number of errors to include in the summary.
	 * 
	 * @return string
	 */
	public function getErrorSummary($maxErrorCount = 16) {
	 	$log = $this->log;
		
	 	if (!$log) return 'The exception was not provided with a log.';
		
	 	return LogUtils::getErrorSummary($log, $maxErrorCount);
	}
	
	public function __toString() {
		return $this->getErrorSummary();
	}
}