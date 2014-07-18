<?php
namespace Solver\Shake;

/**
 * TODO: PHPDoc.
 * TODO: Due to time constraints some features got cut (this concerns the entire component, not just this interface):
 * - Ability to "import" good parts, while ignoring bad data (say filter out extra fields in Dict without error).
 * - Ability to have defaults instead of logging errors & invalidating.
 * - The "auto boolean" feature in dicts (intended only for reading HTTP fields) is always on, but should be optional.
 * - Custom error overrides.
 * - Multi|format|values (first valid "wins").
 * - Ability to compile formats to optimized PHP code.
 * - Conditional and custom tests and filters (may require a base abstract class).
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
interface Format {
	/**
	 * Tries to extract the canonical representation of the data format from $value (or null if $value doesn't contain
	 * valid data in the required format).
	 * 
	 * @param mixed $value
	 * Value to filter and validate.
	 * 
	 * @param \Solver\Shake\ErrorLog $log
	 * Any errors will be logged here (valid values never log errors, invalid values always log 1 or more errors).
	 * 
	 * @param string $path
	 * Optional (default = null). An optional path (base) to log the errors at. 
	 * 
	 * @return mixed
	 * The canonical (and valid) representation of the data, or null if no such representation could be extracted.
	 */
	public function extract($value, ErrorLog $log, $path = null);
}