<?php
namespace Solver\AccordX;

use Solver\Accord\Action;
use Solver\Logging\StatusLog;
use Solver\Accord\ActionException;

/**
 * An abstract action which guarantees to implementers that they will receive an empty (no events)instance of ExpressLog.
 * Instead of implementing apply(), child classes will provide method implement(), with the same semantics as apply(),
 * but a more specific method signature.
 * 
 * Users are encouraged to use this class as a template for building apply()-wrappers that provide additional guarantees
 * about the log, or treat the input in a structured way etc. to avoid repeated boilerplate throughout many actions
 * following common conventions.
 */
abstract class ExpressAction implements Action {
	/**
	 * {@inheritDoc}
	 * @see \Solver\Accord\Action::apply()
	 */
	final public function apply($input = null, StatusLog $log = null) {
		// Does the given log meet the contract already?
		if ($log instanceof ExpressLog && !$log->hasEvents()) {
			// Yes, it does, we don't need to add further indirection.
			return $this->implement($input, $log);
		} else {
			// No, it doesn't, wrap.
			$logWrapper = new ExpressLog($log);
			
			try {
				return $this->implement($input, $logWrapper);
			} catch (ActionException $e) {
				// We map back to the original log, if the user has thrown an ActionException with the wrapper directly.
				if ($e->getLog() === $logWrapper) {
					throw new ActionException($log, $e);
				} else {
					throw $e;
				}
			}
		}
	}
	
	abstract public function implement($input, ExpressLog $log);
}