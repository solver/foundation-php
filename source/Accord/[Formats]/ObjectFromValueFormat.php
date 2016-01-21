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
use Solver\Accord\ActionUtils as AU;
use Solver\Accord\InternalTransformUtils as ITU;
use Solver\Logging\DelegatingStatusLog;

/**
 * Takes a value (PHP null, PHP scalar, PHP array) and returns an instance of an object implementing the given class,
 * that represents or wraps the value.
 * 
 * The provided class MUST implement interface FromValue, or, alternatively, you MUST provide a factory Transform
 * instance to be used for the conversion (in which case your class doesn't have to implement FromValue).
 * 
 * To satisfy the idempotency rule of Format, if the input is already an object an instance of the given class, it's 
 * returned directly, unmodified (this includes subclasses of the given class).
 * 
 * If the provided value can't be converted to the given class, the result is null and errors will be logged to the
 * provided error log (like with any other format).
 * 
 * TODO: A better name for this would be ValueObjectFormat, but people might be misled into not using it to deserialize
 * entity-like objects (which would be unfortunate, as this is perfect for the job). Think about it.
 */
class ObjectFromValueFormat implements Format, FastAction {	
	use ApplyViaFastApply;
	
	protected $className = null;
	protected $factory = null;

	public function __construct($className = null) {
		if ($className !== null) $this->className = $className;
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		$className = $this->className;
		
		// TODO: Wording?
		if ($className === null) {
			throw new \Exception('ObjectFromValueFormat requires to be configured with a class to be set before it is applied.');
		}
		
		if ($input instanceof $className) {
			return $input;
		}
		
		if ($input === null || is_scalar($input) || is_array($input)) {
			if ($this->factory) {
				$factory = $this->factory;
				
				if ($factory instanceof FastAction) {
					return $factory->fastApply($input, $output, $mask, $events, $path);
				} else {
					return AU::emulateFastApply($factory, $input, $output, $mask, $events, $path);
				}
			} else {
				// TODO: Do we need "FastFromValue"? Or ActionUtils::emulateFastFromValue at least?
				if ($mask) {
					$log = new DelegatingStatusLog(new InternalTempLog($events, $path), $mask);
				} else {
					$log = null;
				}
				
				/* @var $className FromValue */
				try {
					$output = $className::fromValue($input, $log);
					return true;
				} catch (ActionException $e) {
					$output = null;
					return false;
				}
			}
		}
		
		if ($mask & SL::T_ERROR) {
			ITU::addErrorTo($events, $path, 'Please provide a valid primitive value type.');	
		}
		
		$output = null;
		return false;
	}
	
	public function setClass($className) {
		$this->className = $className;
		
		return $this;
	}
	
	// TODO: Document this is optional, and if not specified, we use Class::fromValue($input, $log);
	// We can't require Format here as it won't be idempotent, but maybe talk the transform should meet the other Format
	// rules (do we need another interface for non-idempotent Format? Maybe Transform should be non-idempotent Format
	// and we should have another base class for the less restricted version. Action > Transform > Format.. hmm this 
	// rhymes with an "endpoint action" my Services package. Great potential for synergy here.
	public function setFactory(Transform $factory) {
		$this->factory = $factory;
		
		return $this;
	}
}