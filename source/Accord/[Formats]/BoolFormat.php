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
namespace Solver\Accord;

use Solver\Logging\StatusLog as SL;
use Solver\Accord\InternalTransformUtils as ITU;

/**
 * Returns a PHP boolean value if the input can be interpreted as boolean.
 * 
 * Valid input to produce boolean false:
 * 
 * - (bool) false
 * - (int) 0
 * - (float) 0.0
 * - (string) "", "0", "false" (case sensitive)
 * 
 * Valid input to produce boolean true:
 * 
 * - (bool) true
 * - (int) 1
 * - (float) 1.0
 * - (string) "1", "true" (case sensitive)
 * 
 * TODO: add isTrue() and isFalse().
 */
class BoolFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		switch (\gettype($input)) {
			case 'boolean':
				$output = $input; 
				return true;
			
			case 'integer':
			case 'double':
				if ($input == 0) {
					$output = false;
					return true;
				}
				if ($input == 1) {
					$output = true;
					return true;
				}
				break;
				
			case 'string':
				if ($input === '' || $input === '0' || $input === 'false') { 
					$output = false; 
					return true;
				}
				if ($input === '1' || $input === 'true') {
					$output = true;
					return true;
				}
				break;
		}
		
		if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
		
		if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, 'Please provide a valid boolean.');		
		$output = null; 
		return false;
	}
}