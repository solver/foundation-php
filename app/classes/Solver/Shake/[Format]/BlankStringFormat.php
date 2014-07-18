<?php
namespace Solver\Shake;

/**
 * A blank string is a string which is empty or contains only whitespace. This class is simply a shortcu for the 
 * following configuration:
 * 
 * <code>
 * $blankStringFormat = (new StringFormat)
 * 		->filterTrimWhitespace()
 * 		->testEmpty();
 * </code>
 * 
 * This is useful when a field can either be left blank, or have a specific format, as used with EitherFormat:
 * 
 * <code>
 * $blankOrEmailFormat = (new EitherFormat)
 * 		->attempt(new BlankStringFormat)
 * 		->attempt(new EmailAddressFormat);
 * </code>
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class BlankStringFormat extends StringFormat implements Format {
	public function __construct() {
		// TODO: Reimplement this via extract for extra speed, instead of using tests/filters?
		$this->filterTrimWhitespace()->testEmpty();
	}
}