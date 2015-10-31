<?php
namespace Solver\Accord;

use Solver\Accord\ActionUtils as AU;

// IMPORTANT: This trait is an internal implementation detail. May change or go away without warning.
trait PipelineAny {
	use ApplyViaFastApply;
	
	protected $actions;
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		$success = true;
		
		foreach ($this->actions as $i => $action) {
			if ($action instanceof FastAction) {
				$success = $action->fastApply($input, $output, $mask, $events, $path);
			} else {
				$success = AU::emulateFastApply($action, $input, $output, $mask, $events, $path);	
			}
			
			if (!$success) return false;
		}
		
		return true;
	}
}