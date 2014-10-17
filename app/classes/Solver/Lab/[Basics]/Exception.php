<?php
namespace Solver\Lab;

/**
 * The purpose of this class is to allow $code to be a string (the native \Exception class only allows integers).
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class Exception extends \Exception {
	public function __construct($message = '', $code = 0, $previous = null) {
		parent::__construct($message, (int) $code, $previous);
		$this->code = $code;
	}
}