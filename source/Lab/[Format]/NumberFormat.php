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
namespace Solver\Lab;

/**
 * Accepts integers, floats, and strings formatted as an integer or a float. Numbers as strings will be normalized
 * (whitespace trimmed, trailing zeroes removed etc.), but left as strings to avoid precision loss.
 * 
 * TODO: Add min/max filters, arbitrary precision min/max checks for large numbers in strings. * 
 * TODO: See "ScalarProcessor"/"ScalarFilter" from legacy code for more details and more filters/tests we should add
 * in this class.
 */
class NumberFormat extends AbstractFormat implements Format {
	public function extract($value, ErrorLog $log, $path = null) {
		// We deliberately do not use the result here (a PHP float) as we don't want to lose precision for large string
		// numbers.
		if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) goto error;
		
		if (is_string($value)) {
			// We refuse to process strings which are suspiciously large to hold a usable floating point value, in order
			// to avoid blowing up the application in places where such large (if otherwise valid) number values are not
			// expected, such as databases.
			//
			// TODO: Revise this decision. Ideally we'd normalize the exponent and be able to trim precision for a float
			// to a user supplied limit (for ex. for single/double/quad IEEE floats).
			if (strlen($value) > 128) {
				goto error;
			} else {
				$value = $this->normalizeStringNumber($value);
			}
		}
		
		success:
		return parent::extract($value, $log, $path);
		
		error:
		$log->addError($path, 'Please provide a number.');
		return null;
	}
	
	/**
	 * @param int $min
	 * 
	 * @return self
	 */
	public function testMin($min) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($min) {
			// TODO: Use arbitrary precision semantics for large numbers in strings.
			if ($value + 0 < $min + 0) {
				$log->addError($path, "Please provide a number bigger than or equal to $min.");
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
	public function testMax($max) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($max) {
			// TODO: Use arbitrary precision semantics for large numbers in strings.
			if ($value + 0 > $max + 0) {
				$log->addError($path, "Please provide a number lesser than or equal to $max.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * This is identical as testMin(0), but provides a specialized error message for a common test (positive numbers).
	 * 
	 * @return self
	 */
	public function testPositive() {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) {
			// TODO: Use arbitrary precision semantics for large numbers in strings.
			if ($value + 0 < 0) {
				$log->addError($path, "Please provide a positive number.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * Verifies the number is one of:
	 * 
	 * - A PHP integer.
	 * - A float without a fraction and no larger than 2^53 (above which it can't accurately hold an integer value).
	 * - A positive or negative number in a string without a fraction part, or an exponent notation.
	 * 
	 * For floats, it also verifies the float value is within range for an accurately represented integer value.
	 * @return self
	 */
	public function testInteger() {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) {
			// FILTER_VALIDATE_INT not used because it fails on integers outside PHP's native integer range.
			if (\is_string($this->value) && \preg_match('/\s*[+-]?\d+\s*$/AD', $this->value)) {
				return true;
			}
			
			if (is_int($this->value)) {
				return true;
			}
			
			// 9007199254740992 = 2^53 (bit precision threshold in doubles).
			if (is_float($this->value) && $this->value === floor($this->value) && $this->value <= 9007199254740992) {
				return true;
			}
			
			$log->addError($path, "Please provide an integer.");
			return false;
		}];
		
		return $this;
	}
	
	/**
	 * Corrects the format of a valid string number to avoid redundancies and inconsistencies. The resulting string 
	 * represents the exact same value as the original.
	 * 
	 * This function DOES NOT alter the mantissa/exponent mathematical values, only their formatting.
	 * 
	 * Corrections include:
	 * 
	 * 1) Trims whitespace on both sides of the string.
	 * 2) Drops leading plus sign in front of the mantissa.
	 * 3) Adds plus sign if a sign is missing in front of the exponent.
	 * 4) Drops redundant lead zeroes in the mantissa and exponent.
	 * 5) A missing lead zero in front of a decimal point will be restored for zero integer values (i.e. .10 => 0.10).
	 * 6) Drops trailing zeroes in the decimal fraction.
	 * 7) Drops entire decimal fraction, if it consists of only zeroes.
	 * 8) If the exponent is positive or negative 0, it's dropped.
	 * 9) If present, the exponent E/e is always lowercased to "e".
	 * 
	 * NOTE: The choices above were guided by the ECMA-262 specification for rendering numbers to a string and should
	 * be compatible with the widest range of languages, products and platforms.
	 * 
	 * Examples:
	 * +00012.34000e005 becomes 12.34e+5
	 * -.0123e00 becomes -0.0123
	 * 10.000000E-4 becomes 10e-4
	 *  
	 * This filter operates only on strings representing decimal floating point or integer values. It has no effect
	 * on other strings, or on PHP int/float types. The string value should contain no whitespace or other.
	 * This filter can be used in place of toTypeNumber(), when no precision should be lost from the value before
	 * storing it as a string for later processing or handing it to a third-party service that needs higher precision
	 * than PHP's native number types.
	 */
	protected function normalizeStringNumber($value) {
		if (preg_match('/\s*([+-])?(?|0*(\d+)|0*(\d*)(?:\.0+|\.(\d+?))0*)(?:[Ee]([+-])?(?:0+|0*(\d+)))?\s*$/AD', $value, $matches)) {
			$value = 
				( isset($matches[1]) && $matches[1] === '-' ? '-' : '' ). // integer sign
				( isset($matches[2]) && ($tmp = $matches[2]) !== '' ? $tmp : '0' ). // integer digits
				( isset($matches[3]) && ($tmp = $matches[3]) !== '.' ? '.' . $tmp : '' ). // fraction
				(
					isset($matches[5]) && ($tmp = $matches[5]) !== '' ? ( // exponent digits
						'e'.(isset($matches[4]) && $matches[4] == '-' ? '-' : '+') . $tmp // exponent sign & assembly
					) : ''
				);
		}
		
		return $value;
	}
}