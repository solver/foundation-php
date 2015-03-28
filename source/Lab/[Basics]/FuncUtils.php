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
 * Common operations and utils for functions.
 */
class FuncUtils {
	/**
	 * A simple form of memoization (it doesn't accept or pass arguments to the $producer).
	 * 
	 * Useful as a generic mechanism for creating and passing "lazy evaluated" values, for ex. you can pass in an object
	 * factory and upon first call, a single instance will be produced, cached and returned (and then the cache will be
	 * returned on subsequent calls).
	 *  
	 * #Result: mixed
	 * 
	 * @param callable $producer
	 * () => #Result
	 * 
	 * @return mixed
	 * #Result
	 */
	public static function once($producer) {
		return function () use (& $producer, & $result) {
			if ($producer) {
				$result = $producer();
				$producer = null;
			}
			
			return $result;
		};
	}
	
	/**
	 * Legacy name for method closure(). See self::closure().
	 * 
	 * @deprecated
	 * 
	 * @param mixed $a
	 * @param mixed $b
	 */
	public static function toClosure($a, $b = null) {
		return self::closure($a, $b);
	}
	
	/**
	 * Convert various callables into a type-safe and performant Closure instance.
	 * 
	 * A list of all supported formats (note all legacy callable formats are supported, but also variations that don't
	 * require array wrappers, when you don't need it):
	 * 
	 * Basic:
	 * 
	 * - toClosure($closure): If you pass a closure instance, it's returned unmodified.
	 * - toClosure($object): Object, which must implement magic method __invoke.
	 * - toClosure('functionName'): Closure from a function.
	 * 
	 * Static methods (all variations produce the return result):
	 * 
	 * - toClosure('ClassName', 'methodName')
	 * - toClosure('ClassName::methodName')
	 * - toClosure(['ClassName', 'methodName'])
	 * 
	 * Instance methods (all variations produce the return result):
	 * 
	 * - toClosure($object, 'methodName')
	 * - toClosure([$object, 'methodName'])
	 * 
	 * If you need support for PHP's callable formats, see getClosureFromCallable().
	 * 
	 * @param mixed $a
	 * The parameter is overloaded, see usage examples in the method description.
	 * 
	 * @param mixed $b
	 * Default null. The parameter is overloaded, see usage examples in the method description.
	 * 
	 * @return \Closure|null
	 * Closure equivalent to the input parameters, or null if the input is invalid.
	 */
	public static function closure($a, $b = null) {
		if ($b === null) {
			if ($a instanceof \Closure) return $a;
			
			if (is_object($a)) return (new \ReflectionMethod($a, '__invoke'))->getClosure($a) ?: null;
			
			if (is_string($a)) {
				if (strpos($a, '::')) return (new \ReflectionMethod(...explode('::', $a)))->getClosure() ?: null;
				else return (new \ReflectionFunction($a))->getClosure() ?: null;
			}
			
			if (!is_array($a)) return null;
			
			list($a, $b) = $a;
		}
		
		if (is_string($a)) return (new \ReflectionMethod($a, $b))->getClosure() ?: null;
		else return (new \ReflectionMethod($a, $b))->getClosure($a) ?: null;
	}
}