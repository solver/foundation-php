<?php
namespace Solver\Accord;

use Solver\Logging\DelegatingStatusLog;

class ActionUtils {
	/**
	 * Implements the calling conventions of FastAction::fastApply() for any Action.
	 * 
	 * Note that by using this emulation you don't gain the performance benefits of a natively implemented fastApply().
	 * This method is provided to reduce code duplication when working with a hybrid mix of Action and FastAction 
	 * instances. This method detects when it's given a FastAction instance and takes the faster route in this case, but
	 * callers are advised to do the check themselves and do a direct call for better performance, when practical.
	 * 
	 * @param Action $action
	 * See FastAction::fastApply().
	 * 
	 * @param mixed $input
	 * See FastAction::fastApply().
	 * 
	 * @param mixed & $output
	 * See FastAction::fastApply().
	 * 
	 * @param number $mask
	 * See FastAction::fastApply().
	 * 
	 * @param array & $events
	 * See FastAction::fastApply().
	 * 
	 * @param array $path 
	 * See FastAction::fastApply().
	 */
	public static function emulateFastApply(Action $action, $input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		if ($action instanceof FastAction) {
			return $action->fastApply($input, $output, $mask, $events, $path);
		} else {
			if ($mask) {
				// TODO: Eliminate log nesting here (implement as a flat simple log) for speed. Maybe pass events by reference.
				$log = new DelegatingStatusLog(new InternalTempLog($events, $path), $mask);
			} else {
				$log = null;
			}
			
			try {
				$output = $action->apply($input, $log);
				return true;
			} catch (ActionException $e) {
				$output = null;
				return false;
			}
		}
	}
}