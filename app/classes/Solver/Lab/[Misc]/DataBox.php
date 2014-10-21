<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
 * Reading information from deeply nested arrays, often coming from untrusted sources may mean a lot of isset() & data
 * format checks. This class wraps an array & provides a small set of convenience methods to address your most common 
 * needs in such cases.
 * 
 * Controller's $input and View's $data properties come pre-wrapped with this class.
 * 
 * You can't modify an array once it's in a data box, but you can obtain a copy of the array to play with via method
 * unbox().
 */
class DataBox {
	/**
	 * @var array
	 */
	protected $data;
	
	public function __construct($data) {
		$this->data = $data;
	}
	
	/**
	 * Returns a copy of the entire contained array.
	 * 
	 * @return array
	 */
	public function unbox() {
		return $this->data;
	}
	
	/**
	 * Returns the value at the given path (or a default value).
	 * 
	 * @param string $path
	 * Path (key) to the array value you want, use dots to describe the key to a nested array. For example "foo.bar.baz"
	 * will fetch key ["foo"]["bar"]["baz"] from the contained array.
	 * 
	 * @param mixed $default
	 * Optional (default = null). A value that will be returned if the value doesn't exist, or is null.
	 * 
	 * @return mixed
	 * Returns the array value at that path, or the default if it's not set, or null.
	 */
	public function get($path, $default = null) {
		// TODO: Can be optimized.
		$value = ArrayUtils::drill($this->data, $path, $keyOut);
		
		if ($keyOut !== null && isset($value[$keyOut])) {
			return $value[$keyOut];
		} else {
			return $default;
		}
	}
	
	/**
	 * Returns the array at the given path (or a default value).
	 * 
	 * @param string $path
	 * Path (key) to the array value you want, use dots to describe the key to a nested array. For example "foo.bar.baz"
	 * will fetch key ["foo"]["bar"]["baz"] from the contained array.
	 * 
	 * @param mixed $default
	 * Optional (default = null). A value that will be returned if the value doesn't exist, or is not an array.
	 * 
	 * @return mixed
	 * Returns the array value at that path, or the default if it's not set, or is not an array.
	 */
	public function getArray($path, $default = null) {
		$value = $this->get($path);
		
		if (\is_array($value)) return $value;
		else return $default;
	}
	
	/**
	 * Returns the string or string representation (for float/int/bool) at the given path (or a default value).
	 * 
	 * Note that arrays have no string representation, you'll get the default value instead.
	 * 
	 * @param string $path
	 * Path (key) to the array value you want, use dots to describe the key to a nested array. For example "foo.bar.baz"
	 * will fetch key ["foo"]["bar"]["baz"] from the contained array.
	 * 
	 * @param mixed $default
	 * Optional (default = null). A value that will be returned if the value doesn't exist, or is not a scalar value.
	 * 
	 * @return mixed
	 * Returns the array value at that path, or the default if it's not set, or is not a scalar value.
	 */
	public function getString($path, $default = null) {
		$value = $this->get($path);
		
		if (\is_scalar($value)) {
			return (string) $value;
		}
		
		return $default;
	}
	
	/**
	 * Returns the string or string representation (for float/int/bool) at the given path (or a default value), if it
	 * is an exact match of one of the given list of valid values.
	 * 
	 * Note that arrays have no string representation, you'll get the default value instead.
	 * 
	 * @param string $path
	 * Path (key) to the array value you want, use dots to describe the key to a nested array. For example "foo.bar.baz"
	 * will fetch key ["foo"]["bar"]["baz"] from the contained array.
	 * 
	 * @param array $listOfValues
	 * A list of values that the fetched value should be one of. Your values don't have to be passed as strings, but
	 * keep in mind that for comparison purposes they'll be converted & compared as strings with this method.
	 * 
	 * @param mixed $default
	 * Optional (default = null). A value that will be returned if the value doesn't exist, or is not a scalar value.
	 * 
	 * @return mixed
	 * Returns the array value at that path, or the default if it's not set, or is not a scalar value.
	 */
	public function getStringOneOf($path, array $listOfValues, $default = null) {
		$value = $this->getString($path);
		
		// We don't use in_array(), because we want to make sure everything is converted and compared as strings.
		foreach ($listOfValues as $listValue) {
			if ($value === (string) $listValue) return $value;
		}
		
		return $default;
	}
	
	/**
	 * Returns the value at the given path if it passes validation (or a default value). What passes validation:
	 * 
	 * - Strings containing only digits, optionally surrounded by whitespace.
	 * - Positive float values without a fractional part.
	 * - Positive integer values.
	 * 
	 * The plus sign and whitespace, if any, will be stripped from the returned value.
	 * 
	 * @param string $path
	 * Path (key) to the array value you want, use dots to describe the key to a nested array. For example "foo.bar.baz"
	 * will fetch key ["foo"]["bar"]["baz"] from the contained array.
	 * 
	 * @param mixed $default
	 * Optional (default = null). A value that will be returned if the value doesn't exist, or is not digits.
	 * 
	 * @return mixed
	 * Returns the array value at that path, or the default if it's not set, or is not digits.
	 */
	public function getDigits($path, $default = null) {
		$value = $this->get($path);
		
		if (\is_string($value)) {
			$value = \trim($value);
			if (\ctype_digit($value)) {
				return $value;
			}
		}
		
		if (\is_int($value) && $value >= 0) {
			return $value;
		}
		
		if (\is_float($value) && $value === \round($value) && $value >= 0) {
			return $value;
		}
		
		return $default;
	}
	
	public function offsetExists($offset) {
		return isset($this->data[$offset]);
	}

	public function offsetGet($offset) {
		return $this->data[$offset];
	}

	public function offsetSet($offset, $value) {
		$this->errorReadOnly();
	}
	
	public function offsetUnset($offset) {
		$this->errorReadOnly();
	}
	
	private function errorReadOnly() {
		throw new \Exception('This array is wrapped in an instance of Solver\Lab\DataBox, which makes it read-only. You can get a copy of the entire array via method unbox() and modify the copy instead.');
	}
}