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

/**
 * If the input value is a PHP object, it's returned unmodified. Methods are provided to enforce the object type(s).
 * 
 * TODO: Consider isNotInstanceOf, isInstanceOfAll, isInstanceOfAny variations etc.
 */
class ObjectFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	protected $functions = [];

	public function __construct($className = null) {
		if ($className !== null) $this->isInstanceOf($className);
	}
	
	public function isInstanceOf($className) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($className) {
			if (!$input instanceof $className) {
				if ($mask & SL::ERROR_FLAG) ITU::errorTo($events, $path, 'Please provide an instance of ' . $className. '.');
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
		if (!\is_object($input)) {
			if ($mask & SL::ERROR_FLAG) ITU::errorTo($events, $path, 'Please provide a valid object.');
			$output = null;
			return false;
		}	
	
		if ($this->functions) {
			return ITU::fastApplyFunctions($this->functions, $input, $output, $mask, $events, $path);
		} else {
			$output = $input;
			return true;
		}
	}
}