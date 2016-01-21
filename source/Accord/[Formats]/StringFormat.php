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

use Solver\Logging\StatusLog as SL;
use Solver\Accord\InternalTransformUtils as ITU;
use Solver\Toolbox\StringUtils;
use Solver\Toolbox\RegexUtils;

/**
 * A format class for UTF8 encoded strings.
 */
class StringFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	protected $functions = [];
	
	/**
	 * @param string $form
	 * 
	 * @return $this
	 */
	public function normalize($form = StringUtils::NFC) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($form) {
			$output = StringUtils::normalize($input, $form);
			return true;
		};
		
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function trim() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			$output = StringUtils::trim($input);
			return true;
		};
		
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function toUpper() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			$output = StringUtils::toUpper($input);
			return true;
		};
		
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function toLower() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			$output = StringUtils::toLower($input);
			return true;
		};
		
		return $this;
	}
	
	/**
	 * @param $map
	 * A dictionary of string keys and string values (a value matching a key is mapped to its value). On no match, the
	 * value remains unmodified.
	 * 
	 * @return $this
	 */
	public function map(array $map) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($map) {
			if (isset($map[$input])) {
				$output = $map[$input];
			} else {
				$output = $input;
			}
			return true;
		};
		
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function regexReplace($regexPattern, $replacementString) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($regexPattern, $replacementString) {
			// Deliberately disallow callback replacements for now, until we see how to support it cross-platform.
			$output = RegexUtils::replace($input, $regexPattern, (string) $replacementString);
			return true;
		};
		
		return $this;
	}
	
	/**
	 * @param int $length
	 * @return $this
	 */
	public function hasLength($length) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($length) {
			if (StringUtils::length($input) !== $length) {
				if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, "Please use exactly $length characters.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	/**
	 * @param int $lengthMin
	 * @return $this
	 */
	public function hasLengthMin($lengthMin) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($lengthMin) {
			if (StringUtils::length($input) < $lengthMin) {
				if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, "Please use at least $lengthMin characters.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	/**
	 * @param int $lengthMax
	 * @return $this
	 */
	public function hasLengthMax($lengthMax) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($lengthMax) {
			if (StringUtils::length($input) > $lengthMax) {
				if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, "Please use at most $lengthMax characters.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	/**
	 * A convenience shortcut combining hasLengthMin() and hasLengthMax().
	 * 
	 * @param int $lengthMin
	 * @param int $lengthMax
	 * @return $this
	 */
	public function hasLengthInRange($lengthMin, $lengthMax) {
		$this->hasLengthMin($lengthMin);
		$this->hasLengthMax($lengthMax);
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function isNotEmpty() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			if ($input === '') {
				if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, "Please fill in.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
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
	 * 		$emailOrBlank = (new OrFormat)
	 * 			->add((new StringFormat)
	 * 				->doTrim()
	 * 				->isEmpty())
	 * 			->add(new EmailAddressFormat);
	 * </code>
	 * 
	 * Also see BlankStringFormat.
	 * 
	 * @return $this
	 */
	public function isEmpty() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			if ($input !== '') {
				// If added first in a OrFormat, this awkward message won't be seen.
				if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, "Please leave empty.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
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
	 * @return $this
	 */
	public function isOneOf(array $list, $displayListInMessage = false) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($list, $displayListInMessage) {
			foreach ($list as $item) if ($input === (string) $item) {
				$output = $input;
				return true;
			}
			
			if ($mask & SL::T_ERROR) {
				if ($displayListInMessage) {
					ITU::addErrorTo($events, $path, 'Please use one of the following values: "' . \implode('", "', $list) . '".');
				} else {
					ITU::addErrorTo($events, $path, 'Please fill in a valid value.');
				}
			}
			
			$output = null;
			return false;
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
	 * @return $this
	 */
	public function isEqualTo($input, $displayListInMessage = false) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($input, $displayListInMessage) {
			if ($input === (string) $input) {
				$output = $input;
				return true;
			}
			
			if ($mask & SL::T_ERROR) {
				if ($displayListInMessage) {
					ITU::addErrorTo($events, $path, 'Please fill in "' . $input . '".');
				} else {
					ITU::addErrorTo($events, $path, 'Please fill in a valid value.');
				}
			}
			
			$output = null;
			return false;
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
	 * @return $this
	 */
	public function isRegexMatch($regexPattern, $customError = null) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($regexPattern, $customError) {
			if (!RegexUtils::match($input, $regexPattern)) {
				if ($mask & SL::T_ERROR) {
					$event = ['type' => 'error', 'message' => 'Please fill in a valid value.'];
					if ($path) $event['path'] = $path;
					if ($customError) $event = $customError + $event;
					
					$events[] = $event;
				}
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		if (!\is_string($input)) {
			// We tolerate certain scalars by automatically converting them to strings.
			if (\is_int($input) || \is_float($input) || \is_bool($input)) {
				$output = (string) $input;
			} else {
				if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
				if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, 'Please provide a string.');
				$output = null;
				return false;
			}
		}
	
		if ($this->functions) {
			return ITU::fastApplyFunctions($this->functions, $input, $output, $mask, $events, $path);
		} else {
			return true;
		}
	}
}