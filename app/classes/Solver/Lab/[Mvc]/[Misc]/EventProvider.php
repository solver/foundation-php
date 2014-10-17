<?php
namespace Solver\Lab;

/**
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
interface EventProvider {
	/**
	 * Returns a list of dicts, each having these keys:
	 * 
	 * - string "type"
	 * - string "path"
	 * - string "message"
	 * - string "code"
	 * - array "details"
	 */
	public function getAllEvents();
}