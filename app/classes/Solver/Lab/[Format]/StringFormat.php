<?php
namespace Solver\Shake;

/**
 * TODO: PHPDoc.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class StringFormat extends AbstractFormat implements Format {
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_string($value)) {
			// We tolerate certain scalars by automatically converting them to strings.
			if (\is_int($value) || \is_float($value) || \is_bool($value)) {
				$value = (string) $value;
			} else {
				$log->addError($path, 'Please provide a string.');
				return null;
			}
		} 
						
		$value = parent::extract($value, $log, $path);
		
		return $value;
	}
	
	/**
	 * @param string $form
	 * 
	 * @return self
	 */
	public function filterNormalize($form = StringUtils::NFC) {
		$this->rules[] = ['filter', function ($value, ErrorLog $log, $path) use ($form) {
			return StringUtils::normalize($value, $form);
		}];
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function filterTrimWhitespace() {
		$this->rules[] = ['filter', function ($value, ErrorLog $log, $path) {
			return StringUtils::trimWhitespace($value);
		}];
		
		return $this;
	}
	
	/**
	 * @param int $min
	 * 
	 * @return self
	 */
	public function testLengthMin($min) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($min) {
			$length = StringUtils::length($value);
			
			if ($length < $min) {
				$log->addError($path, "Please use at least $min characters.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * @param int $max
	 * 
	 * @return self
	 */
	public function testLengthMax($max) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($max) {
			$length = StringUtils::length($value);
			
			if ($length > $max) {
				$log->addError($path, "Please use at most $max characters.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function testNotEmpty() {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) {
			$length = \count($value);
			
			if ($value === '') {
				$log->addError($path, "Please fill in.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * This is useful when combined with EitherFormat to allow a field to either be an empty string or some other
	 * value, for ex. for an email form field, where it's acceptable to leave the field blank:
	 * 
	 * <code>
	 * 		$optionalEmail = (new EitherFormat)
	 * 			->attempt((new StringFormat)
	 * 				->filterTrimWhitespace()
	 * 				->testEmpty())
	 * 			->attempt(new EmailAddressFormat);
	 * </code>
	 * 
	 * Also see BlankStringFormat which automatically applies the above filter & test, so the above can be shortened to:
	 * 
	 * <code>
	 * 	$optionalEmail = (new EitherFormat)
	 * 			->attempt(new BlankStringFormat)
	 * 			->attempt(new EmailAddressFormat);
	 * </code>
	 * 
	 * @return self
	 */
	public function testEmpty() {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) {
			if ($value !== '') {
				$log->addError($path, "Please leave blank.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * Tests if the value matches one of the passed values. The values are compared as strings, strictly.
	 * 
	 * @param array $list
	 * A list of strings.
	 * 
	 * @param bool $displayListInMessage
	 * Optional (default = false). Pass true if you want the error message to show the list of valid values. You'd want
	 * to leave that disabled if the list of values may be too long to show in a human readable message, or the kind of 
	 * values in the list aren't human readable anyway.
	 * 
	 * @return self
	 */
	public function testOneOf(array $list, $displayListInMessage = false) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($list, $displayListInMessage) {
			foreach ($list as $item) if ($value === (string) $item) {
				return true;
			}
			
			if ($displayListInMessage) {
				$log->addError($path, 'Please use one of the following values: "' . \implode('", "', $list) . '".');
			} else {
				$log->addError($path, 'Please fill in a valid value.');
			}
			
			return false;
		}];
		
		return $this;
	}
}