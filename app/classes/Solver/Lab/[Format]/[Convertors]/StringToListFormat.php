<?php
namespace Solver\Lab;

/**
 * This format falls in the group of "convertors". Simple formats which just take one kind of format and output another,
 * without having any support for customizable filters and tests.
 * 
 * Turns a string into a list based on a set of delimiter chars. The behavior of this particular class is optimized
 * for human-typed lists into a text field.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class StringToListFormat implements Format {
	protected $delimiters;
	
	/**
	 * @param string $delimiters
	 * Optional (default = ',;\r\n\t'). One or more characters, either of which will act on its own as a delimiter, hence a
	 * splitting point for creating the list. A sequence of multiple delimiter characters will not create empty 
	 * element, neither will leading or trailing delimiter chars in the string.
	 */
	public function __construct($delimiters = ",;\r\n\t ") {
		// Pre-escape for inclusion in regex.
		$this->delimiters = \preg_quote($delimiters, '@');
	}
	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_string($value)) {
			$log->addError($path, 'Please supply a string.');
			return null;
		} else {
			return \preg_split('@[ ' . $this->delimiters . ']+@', $value, 0, \PREG_SPLIT_NO_EMPTY);
		}
	}
}