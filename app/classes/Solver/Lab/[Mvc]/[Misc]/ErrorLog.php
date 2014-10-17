<?php
namespace Solver\Shake;

/**
 * A log capable of holding error events.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
interface ErrorLog {
	public function addError($path = null, $message = null, $code = null, $details = null);
	
	// TODO: Maybe this shouldn't be here.
	public function hasErrors();
	
	// TODO: Maybe this shouldn't be anywhere.
	public function getErrorCount();
}