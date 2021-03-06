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
namespace Solver\Web;

/**
 * Provides call*() methods, which directly bind an input value to a call of a public method by the same name.
 * 
 * @property PageInput $input
 */
trait CallPageTrait {
	/**
	 * Reads an input value and calls the controller method by that name (if it exists).
	 * 
	 * To qualify for binding:
	 * 
	 * - The method should be public (this way you can differentiate "action" from "non-action" methods by making the 
	 * latter protected/private).
	 * - The action should not begin with double underscore (reserved for PHP's special methods).
	 * - The action should be not be called "main" (method "main" is part of the controller interface).
	 * - If the action contains dashes they're converted to camelcase like so: "foo-bar-baz" becomes "fooBarBaz".
	 * 
	 * @param string $inputPath
	 * Input path to read the value from. Typical examples include "tail.0" (URL actions), "body.action" (form actions).
	 * 
	 * @param string $default
	 * Optional (default = null). Method name you'd like to call if the input value at the given path doesn't exist.
	 * 
	 * The default method will be called only when the value is missing. If it's present, but its value doesn't map to a
	 * public method, nothing will be called (and you'll get false as a return result).
	 * 
	 * @return bool
	 * True if an applicable method was found and called, false if it wasn't.
	 */
	protected function call($inputPath, $default = null) {
		$value = $this->input->getString($inputPath, $default);
		
		return $this->callInternal($value);
	}
	
	/**
	 * The same as call(), with one additional rule: the method name should be present in the passed list of allowed
	 * method names.
	 * 
	 * @param string $inputPath
	 * Input path to read the value from. Typical examples include "tail.0" (URL actions), "body.action" (form actions).
	 * 
	 * @param string $whitelist
	 * A list of allowed method names (as strings). 
	 * 
	 * @param string $default
	 * Optional (default = null). Method name you'd like to call if the input value at the given path doesn't exist.
	 * 
	 * The default method will be called only when the value is missing. If it's present, but its value doesn't map to a
	 * public method, nothing will be called (and you'll get false as a return result).
	 * 
	 * @return bool
	 * True if an applicable method was found and called, false if it wasn't.
	 */
	protected function callIfOneOf($inputPath, array $whitelist, $default = null) {
		// We don't use getStringOneOf here because we differentiate missing values from values not in the whitelist.
		$value = $this->input->getString($inputPath, $default);
		
		// Manual check instead.
		if (!\in_array($value, $whitelist, true)) return false;
		
		return $this->callInternal($value);
	}
	
	/**
	 * The same as call(), with one additional rule: the method name shouldn't be present in the passed list of 
	 * disallowed method names.
	 * 
	 * @param string $inputPath
	 * Input path to read the value from. Typical examples include "tail.0" (URL actions), "body.action" (form actions).
	 * 
	 * @param string $blacklist
	 * A list of disallowed method names (as strings). 
	 * 
	 * @param string $default
	 * Optional (default = null). Method name you'd like to call if the input value at the given path doesn't exist.
	 * 
	 * The default method will be called only when the value is missing. If it's present, but its value doesn't map to a
	 * public method, nothing will be called (and you'll get false as a return result).
	 * 
	 * @return bool
	 * True if an applicable method was found and called, false if it wasn't.
	 */
	protected function callIfNotOneOf($inputPath, array $blacklist, $default = null) {
		// We don't use getStringOneOf here because we differentiate missing values from values not in the whitelist.
		$value = $this->input->getString($inputPath, $default);
		
		// Manual check instead.
		if (\in_array($value, $blacklist, true)) return false;
		
		return $this->callInternal($value);
	}
	
	private function callInternal($value) {
		if ($value === null || $value === 'main') return false;
		if (isset($value[1]) && $value[0] === '_' && $value[1] === '_') return false;
		
		$value = explode('-', $value);
		
		for ($i = 1, $m = count($value); $i < $m; $i++) {
			$value[$i] = ucfirst($value[$i]);
		} 
		
		$value = implode('', $value);
		
		$class = new \ReflectionClass(\get_class($this));
			
		try {
			$method = $class->getMethod($value);
			
			// PHP's methods (and hence getMethod()'s behavior) are not case-sensitive. But we are.
			if ($value !== $method->getName()) return false;
			
			if ($method->isPublic()) $method->invoke($this);
		} catch (\ReflectionException $e) {
			return false;
		}
		
		return true;
	}
}