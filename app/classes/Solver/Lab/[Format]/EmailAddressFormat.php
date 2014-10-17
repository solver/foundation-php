<?php
namespace Solver\Lab;

/**
 * Returns an email address string if the input can be interpreted as such.
 * 
 * Whitespace is automatically trimmed.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class EmailAddressFormat extends StringFormat implements Format {	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_string($value)) {
			$this->errorBadEmail($log, $path);
			return null;
		}
		
		$value = StringUtils::trimWhitespace($value);
		
		// TODO: This is RFC 5322 check, verify it's RFC 6530 compliant: http://en.wikipedia.org/wiki/Email_address#Internationalization
		if ($value === '' || (\strlen($value) > 320 || !\preg_match('/^[\w%.\'!#$&*+\/=?^`{|}~-]{1,64}@(?:(?:\d+\.){3}\d+|(?:[a-z\d][a-z\d-]+)+(?:\.[a-z\d][a-z\d-]+)+)$/iD', $value))) {
			$this->errorBadEmail($log, $path);
			return null;
		}
		
		$value = parent::extract($value, $log, $path);
		
		return $value;
	}
	
	protected function errorBadEmail(ErrorLog $log, $path) {
		$log->addError($path, 'Please fill in a valid email address.');
	}
}