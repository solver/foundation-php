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
 * A reusable API for creating get/set properties (and often used variations, such as read-only properties etc.).
 * 
 * The API is reminiscent of JavaScript's object.defineProperty(), while geared to fit PHP better.
 */
trait Properties {
	/**
	 * A map of registered properties (long name as typical in traits to avoid collisions).
	 * 
	 * @var array
	 */
	protected $solverLabProperties;
	
	/**
	 * Registers a property for handling with get/set. The property handlers will only fire if the variable is not set
	 * on the class. You can either not declare it, or declare it & unset it (the latter allows you to switch from using
	 * handlers to direct access dynamically at runtime as you need, it also allows IDEs to see the public properties 
	 * you'll have as you work with code).
	 * 
	 * @param string $name
	 * Name of the property to create.
	 * 
	 * @param bool|string|\Closure $get
	 * One of the following:
	 * 
	 * - bool true: Sets handler by convention, for example for property "foo" the handler method will be "getFoo".
	 * - bool false: Sets a handler which forbids accessing the property (issues a fatal error).
	 * - string "name": Sets the handler to an instance method of the given name.
	 * - string "$name": Sets a handler which returns the value of an instance property of the given name.
	 * - \Closure: Sets a custom handler via a closure.
	 * 
	 * Handler functions/methods may optionally accept a single parameter $name, if you need it, and must return the 
	 * value to be used as the value property.
	 * 
	 * @param bool|string|\Closure $set
	 * One of the following:
	 * 
	 * - bool true: Sets handler by convention, for example for property "foo" the handler method will be "setFoo".
	 * - bool false: Sets a handler which forbids modifying the property (issues a fatal error).
	 * - string "name": Sets the handler to an instance method of the given name.
	 * - string "$name": Sets a handler which modifies an instance property of the given name.
	 * - \Closure: Sets a custom handler via a closure.
	 * 
	 * Handler functions/methods must accept $value as their first parameter, and may optionally accept a second
	 * parameter $name, if you need it. The return result is ignored.
	 */
	protected function defineProperty($name, $get, $set) {
		if ($get === false && $set === false) {
			throw new \Exception('Cannot create property "'. $name . '" with forbidden both access and modification.');
		}
		
		if (isset($this->solverLabProperties[$name])) {
			throw new \Exception('Cannot create property "'. $name . '" as it already exists.');
		}
		
		$property = [];
		
		/*
		 * Getter.
		 */
		
		// Default "access forbidden" get handler.
		if ($get === false) {
			$property['get'] = function () use ($name) {
				throw new \Exception('Cannot access write-only property "'. $name . '".');
			};
		}
		
		// Default "getName()" callback. 
		elseif ($get === true) {
			$property['get'] = (new \ReflectionMethod($this, 'get' . \ucfirst($name)))->getClosure($this);
		}
		
		// Custom get callback.
		elseif ($get instanceof \Closure) {
			$property['get'] = $get;
		}
		
		// Retrieve custom property / custom instance method.
		elseif (\is_string($get)) {
			if ($get[0] === '$') {
				$varName = \substr($get, 1);
				$property['get'] = function () use ($varName) {
					return $this->{$varName};
				};
			} else {
				$property['get'] = (new \ReflectionMethod($this, $name))->getClosure($this);
			}
		}
		
		else {
			throw new \Exception('Invalid getter specification.');
		}
		
		/*
		 * Setter.
		 */
		
		// Default "modification forbidden" set handler.
		if ($set === false) {
			$property['set'] = function () use ($name) {
				throw new \Exception('Cannot modify read-only property "'. $name . '".');
			};
		}
		
		// Default "setName()" callback. 
		elseif ($set === true) {
			$property['set'] = (new \ReflectionMethod($this, 'set' . \ucfirst($name)))->getClosure($this);
		}
		
		// Custom set callback.
		elseif ($set instanceof \Closure) {
			$property['set'] = $set;
		}
		
		// Set custom property / custom instance method.
		elseif (\is_string($set)) {
			if ($set[0] === '$') {
				$varName = \substr($set, 1);
				$property['set'] = function () use ($varName) {
					return $this->{$varName};
				};
			} else {
				$property['set'] = (new \ReflectionMethod($this, $name))->getClosure($this);
			}
		}
		
		else {
			throw new \Exception('Invalid setter specification.');
		}
				
		$this->solverLabProperties[$name] = $property;
	}
	
	public function __set($name, $value) {
		if (isset($this->solverLabProperties[$name])) {
			$this->solverLabProperties[$name]['set']($value, $name);
		}
		
		// Emulate the default behavior, which allows dynamic properties.
		// TODO: Consider adding API for sealing properties (to disallow dynamic ones to be set on the object).
		else {
			$this->{$name} = $value;
		}
	}
	
	public function __get($name) {
		if (isset($this->solverLabProperties[$name])) {
			return $this->solverLabProperties[$name]['get']($name);
		}
		
		// Emulate the default behavior, which will result in the standard PHP Notice.
		else {
			return $this->{$name};
		}
	}
}