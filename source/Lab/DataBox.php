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

use Solver\Toolbox\CollectionUtils;

/**
 * A convenient interface for reading & manipulating information from deeply nested arrays, often coming from untrusted
 * sources, with unverified structure (i.e. provides safe behavior in case of missing keys etc., avoiding the need for
 * heavy isset() use & common data format checks).
 */
class DataBox {
	/**
	 * @var array
	 */
	protected $data;
	
	public function __construct($data = []) {
		$this->data = $data;
	}
	
	/**
	 * Replaces the entire internal array with the given array.
	 * 
	 * @param array $data
	 */
	public function replaceAll(array $data) {
		$this->data = $data;
	}
	
	/**
	 * Resets the internal data to an empty array.
	 */
	public function removeAll() {
		$this->data = [];
	}
	
	/**
	 * Returns a copy of the entire contained array.
	 * 
	 * @return array
	 */
	public function getAll() {
		return $this->data;
	}
	
	// TODO: Document.
	public function has($path) {
		$parent = CollectionUtils::drill($this->data, $path, $keyOut, true);
		
		if ($parent !== null && isset($parent[$keyOut])) {
			return true;
		} else {
			return false;
		}
	}
	
	// TODO: Document.
	public function set($path, $value) {
		$parent = & CollectionUtils::drill($this->data, $path, $keyOut, true, true);
		$parent[$keyOut] = $value;
		if ($parent !== null) {
			$parent[$keyOut] = $value;
		}
	}
	
	// TODO: Document.
	public function push($path, $value) {
		$parent = & CollectionUtils::drill($this->data, $path, $keyOut, true, true);
		
		if (!isset($parent[$keyOut]) || !is_array($parent[$keyOut])) {
			$parent[$keyOut] = [];
		}
		
		$parent[$keyOut][] = $value;
	}
	
	// TODO: Document.
	public function remove($path) {
		$parent = & CollectionUtils::drill($this->data, $path, $keyOut);
		
		if ($parent !== null && key_exists($keyOut, $parent)) {
			unset($parent[$keyOut]);
		}
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
		$parent = CollectionUtils::drill($this->data, $path, $keyOut);
		
		if ($parent !== null && isset($parent[$keyOut])) {
			return $parent[$keyOut];
		}
		
		return $default;
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
		
		if (\is_array($value)) {
			return $value;
		} 
		
		return $default;
	}
	
	/**
	 * Returns the string or a string representation (for float/int/bool) at the given path (or a default value).
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
	 * - Strings containing only digits, optionally surrounded by whitespace (whitespace will be trimmed).
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
}