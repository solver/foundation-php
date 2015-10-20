<?php
namespace Solver\Accord;

use Solver\Logging\Log;

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
	protected $log;
	
	/**
	 * @param \Solver\Logging\Log $log
	 * Log of the failing action. Optional.
	 * 
	 * @param \Exception $previous
	 * Previous exception. Optional.
	 */
	public function __construct(Log $log = null, \Exception $previous = null) {
		$this->log = $log;
		
		parent::__construct(null, null, $previous);
	}
	
	/**
	 * @return \Solver\Logging\Log|null
	 * The log of the failed action (if any). This method may return null. Please do heed the warning about using the
	 * log provided at the head doc comment in this class ("IMPORTANT: It's best to use the included log...").
	 */
	public function getLog() {
		return $this->log;
	}
}