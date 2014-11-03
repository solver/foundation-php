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
 * TODO: PHPDoc.
 */
class ListFormat extends AbstractFormat implements Format {
	protected $rules = [];
	protected $childrenFormat = null;
	
	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_array($value)) {
			$log->addError($path, 'Please provide a list.');
			return null;
		}
		
		$errorCount = $log->getErrorCount();
		$childrenFormat = $this->childrenFormat;
		$filtered = [];
				
		// Extract list (we require a sequential, zero based list, anything else isn't a list, the order of the actual
		// keys in the input, however is not important /the result will be sorted/).
		for ($i = 0;; $i++) {
			if (\key_exists($i, $value)) {
				$filtered[$i] = $childrenFormat 
					? $childrenFormat->extract($value[$i], $log, $path === null ? $i : $path . '.' . $i) 
					: $value[$i];
			} else {
				break;
			}
		}
		
		$value = $filtered;
		
		if ($log->getErrorCount() > $errorCount) {
			return null;
		} else {
			return parent::extract($value, $log, $path);
		}
	}
	
	/**
	 * @param \Solver\Lab\Format $format
	 * 
	 * @return self
	 */
	public function children(Format $format) {
		if ($this->rules) throw new \Exception('You should call method children() before any test*() or filter*() calls.');
		if ($this->childrenFormat !== null) throw new \Exception('You should call method children() only once.');
		
		$this->childrenFormat = $format;
		
		return $this;
	}
	
	/**
	 * @param int $min
	 * 
	 * @return self
	 */
	public function testLengthMin($min) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($min) {
			$count = \count($value);
			
			if ($count < $min) {
				$noun = $min == 1 ? 'item' : 'items';
				$log->addError($path, "Please provide a list with at least $min $noun.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * @param int $max
	 * 
	 * @return self
	 */
	public function testLengthMax($max) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($max) {
			$count = \count($value);
			
			if ($count > $max) {
				$noun = $min == 1 ? 'item' : 'items';
				$log->addError($path, "Please provide a list with at most $max $noun.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function testNotEmpty() {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) {
			$count = \count($value);
			
			if ($count == 0) {
				$log->addError($path, "Please provide a list with one or more items.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
}