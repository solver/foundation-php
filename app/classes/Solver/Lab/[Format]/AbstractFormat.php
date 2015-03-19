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
 * Base for formats.
 * 
 * DO NOT extend this class in classes outside this library. It will likely be refactored as a trait in the future.
 * 
 * Instead, implement interface Format directly.
 */
abstract class AbstractFormat implements Format {
	protected $rules = [];
	
	public function extract($value, ErrorLog $log, $path = null) {
		
		foreach ($this->rules as $rule) {
			list($type, $closure) = $rule;
			
			switch ($type) {
				case 'test':
					if (!$closure($value, $log, $path)) return null;
					break;
				
				case 'filter':
					$value = $closure($value, $log, $path);
					break;
					
				default:
					// Catch developer mistakes when adding a rule.
					throw new \Exception('Bad type.'); 
			}
		}
		
		return $value;
	}
		
	/**
	 * The closure must accept arguments ($value, ErrorLog $log, $path) and do one of these:
	 * 
	 * - If the value passes the test, do not log any errors.
	 * - If the value doesn't pass the test, log one or more errors (mind the $path given while logging).
	 * 
	 * @return self
	 */
	public function test(\Closure $test) {
		// Internally tests must return true/false, hence this wrapper to emulate that. Eventually all test 
		// functions, including the internal ones, will work only with the log without having to any return result.
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($test) {
			$errorCount = $log->getErrorCount();
			$test($value, $log, $path);
			return $log->getErrorCount() == $errorCount;
		}];
		
		return $this;
	}
	
	/**
	 * The old name for test().
	 * 
	 * @deprecated
	 * @param \Closure $test
	 * @return self
	 */
	public function testCustom(\Closure $test) {
		return $this->test($test);
	}
	
	/**
	 * The closure must accept argument ($value) and return the modified $value.
	 * 
	 * @return self
	 */
	public function filter(\Closure $filter) {
		$this->rules[] = ['filter', $filter];
		
		return $this;
	}

	
	/**
	 * The old name for filter().
	 * 
	 * @deprecated
	 * @param \Closure $test
	 * @return self
	 */
	public function filterCustom(\Closure $test) {
		return $this->filter($test);
	}
}