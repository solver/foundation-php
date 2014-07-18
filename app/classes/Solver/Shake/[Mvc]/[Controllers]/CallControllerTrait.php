<?php
namespace Solver\Shake;

/**
 * Provides call() and callOneOf(), which directly bind an input value to a call of a public method by the same name.
 */
trait CallControllerTrait {
	/**
	 * Reads an input value and calls the controller method by that name (if it exists).
	 * 
	 * To qualify for binding:
	 * 
	 * - The method should be public (this way you can differentiate "action" from "non-action" methods by making the 
	 * latter protected/private).
	 * - The action should not begin with double underscore (reserved for PHP's special methods).
	 * - The action should be not be called "main" (method "main" is part of the controller interface).
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