<?php
/*
 * Copyright (C) 2011-2015 Solver Ltd. All rights reserved.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on
 * an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 */
namespace Solver\Accord;

use Solver\Logging\ErrorLog;
use Solver\Toolbox\StringUtils;
use Solver\Toolbox\RegexUtils;

/**
 * A format class for UTF8 encoded strings.
 */
class StringFormat implements Format {
	use TransformBase;
	
	protected $functions = [];
	
	/**
	 * @param string $form
	 * 
	 * @return self
	 */
	public function normalize($form = StringUtils::NFC) {
		$this->functions[] = static function ($value, & $errors, $path) use ($form) {
			return StringUtils::normalize($value, $form);
		};
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function trim() {
		$this->functions[] = static function ($value, & $errors, $path) {
			return StringUtils::trim($value);
		};
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function toUpper() {
		$this->functions[] = static function ($value, & $errors, $path) {
			return StringUtils::toUpper($value);
		};
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function toLower() {
		$this->functions[] = static function ($value, & $errors, $path) {
			return StringUtils::toLower($value);
		};
		
		return $this;
	}
	
	/**
	 * @param $map
	 * A dictionary of string keys and string values (a value matching a key is mapped to its value). On no match, the
	 * value remains unmodified.
	 * 
	 * @return self
	 */
	public function map(array $map) {
		$this->functions[] = static function ($value, & $errors, $path) use ($map) {
			if (isset($map[$value])) return $map[$value];
			return $value;
		};
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function regexReplace($regexPattern, $replacementString) {
		$this->functions[] = static function ($value, & $errors, $path) use ($regexPattern, $replacementString) {
			// Deliberately disallow callback replacements for now, until we see how to support it cross-platform.
			return RegexUtils::replace($value, $regexPattern, (string) $replacementString);
		};
		
		return $this;
	}
	
	/**
	 * @param int $length
	 * @return self
	 */
	public function hasLength($length) {
		$this->functions[] = static function ($value, & $errors, $path) use ($length) {
			if (StringUtils::length($value) !== $length) {
				$errors[] = [$path, "Please use exactly $length characters."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	/**
	 * @param int $lengthMin
	 * @return self
	 */
	public function hasLengthMin($lengthMin) {
		$this->functions[] = static function ($value, & $errors, $path) use ($lengthMin) {
			if (StringUtils::length($value) < $lengthMin) {
				$errors[] = [$path, "Please use at least $lengthMin characters."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	/**
	 * @param int $lengthMax
	 * @return self
	 */
	public function hasLengthMax($lengthMax) {
		$this->functions[] = static function ($value, & $errors, $path) use ($lengthMax) {
			if (StringUtils::length($value) > $lengthMax) {
				$errors[] = [$path, "Please use at most $lengthMax characters."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	/**
	 * A convenience shortcut combining hasLengthMin() and hasLengthMax().
	 * 
	 * @param int $lengthMin
	 * @param int $lengthMax
	 * @return self
	 */
	public function hasLengthInRange($lengthMin, $lengthMax) {
		$this->hasLengthMin($lengthMin);
		$this->hasLengthMax($lengthMax);
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function isNotEmpty() {
		$this->functions[] = static function ($value, & $errors, $path) {
			if ($value === '') {
				$errors[] = [$path, "Please fill in."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	/**
	 * This is useful when combined with UnionrFormat to allow a field to either be an empty string (or if you trim
	 * beforehand via doTrim() also any whitespace-only blank string) or some other value, for ex. for an email form
	 * field, where it's acceptable to leave the field blank:
	 * 
	 * <code>
	 * 		$emailOrBlank = (new UnionFormat)
	 * 			->add((new StringFormat)
	 * 				->doTrim()
	 * 				->isEmpty())
	 * 			->add(new EmailAddressFormat);
	 * </code>
	 * 
	 * Also see BlankStringFormat.
	 * 
	 * @return self
	 */
	public function isEmpty() {
		$this->functions[] = static function ($value, & $errors, $path) {
			if ($value !== '') {
				// If added first in a UnionFormat, this awkward message won't be seen.
				$errors[] = [$path, "Please leave empty."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	/**
	 * TODO: isNotOneOf.
	 * 
	 * Tests if the value matches one of the passed values. The values are compared using string semantics.
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
	public function isOneOf(array $list, $displayListInMessage = false) {
		$this->functions[] = static function ($value, & $errors, $path) use ($list, $displayListInMessage) {
			foreach ($list as $item) if ($value === (string) $item) return $value;
			
			if ($displayListInMessage) {
				$errors[] = [$path, 'Please use one of the following values: "' . \implode('", "', $list) . '".'];
			} else {
				$errors[] = [$path, 'Please fill in a valid value.'];
			}
			
			return null;
		};
		
		return $this;
	}
	
	/**
	 * TODO: isNotEqualTo.
	 * 
	 * Tests if the value matches the given input. The values are compared using string semantics.
	 * 
	 * @param string $input
	 * A string to match.
	 * 
	 * @param bool $displayListInMessage
	 * Optional (default = false). Pass true if you want the error message to show the valid value. You'd want to leave
	 * that disabled if the valid value is too long for a human readable message, or is a secret.
	 * 
	 * @return self
	 */
	public function isEqualTo($input, $displayListInMessage = false) {
		$this->functions[] = static function ($value, & $errors, $path) use ($input, $displayListInMessage) {
			if ($value === (string) $input) return $value;
			
			if ($displayListInMessage) {
				$errors[] = [$path, 'Please fill in "' . $input . '".'];
			} else {
				$errors[] = [$path, 'Please fill in a valid value.'];
			}
			
			return null;
		};
		
		return $this;
	}
	
	/**
	 * TODO: isNotRegexMatch.
	 * 
	 * Tests if the value matches the given regex pattern (the regex pattern uses PCRE in UTF8 mode).
	 * 
	 * @param $regexPattern
	 * A string PCRE pattern (with delimiters, as expected in PHP).
	 * 
	 * @param $customError
	 * Optional (default = null). You can pass a dict with one or more of these fields:
	 * 
	 * - $path
	 * - $message
	 * - $code
	 * - $details
	 * 
	 * Doing so will replace the built-in generic "Please fill in a valid value." message in case the match fails.
	 * 
	 * @return self
	 */
	public function isRegexMatch($regexPattern, $customError = null) {
		$this->functions[] = static function ($value, & $errors, $path) use ($regexPattern, $customError) {
			if (!RegexUtils::match($value, $regexPattern)) {
				if ($customError === null) $customError = ['message' => 'Please fill in a valid value.'];
				
				$errors[] = [
					isset($customError['path']) ? $customError['path'] : $path, 
					isset($customError['message']) ? $customError['message'] : null,
					isset($customError['code']) ? $customError['path'] : null,
					isset($customError['details']) ? $customError['details'] : null
				];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	public function apply($value, ErrorLog $log, $path = null) {
		if (!\is_string($value)) {
			// We tolerate certain scalars by automatically converting them to strings.
			if (\is_int($value) || \is_float($value) || \is_bool($value)) {
				$value = (string) $value;
			} else {
				$log->error($path, 'Please provide a string.');
				return null;
			}
		}
	
		if ($this->functions) {
			return $this->applyFunctions($this->functions, $value, $log, $path);
		} else {
			return $value;
		}
	}
}