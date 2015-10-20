<?php
namespace Solver\Accord;

use Solver\Logging\StatusLog;

/**
 * This trait implements Action::apply() for a class which has defined FastAction::fastApply().
 */
trait ApplyViaFastApply {
	abstract public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null);
	
	public function apply($input = null, StatusLog $log = null) {
		// TODO: Remove this once the IDE doesn't complain about undefined param.
		$output = null; 
		$events = null;
		
		if ($this->fastApply($input, $output, $log ? $log->getMask() : 0, $events)) {
			if ($events) $log->log(...$events);
			return $output;
		} else {
			if ($events) $log->log(...$events);
			throw new ActionException($log);
		}
	}
}