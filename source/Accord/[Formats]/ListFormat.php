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

use Solver\Logging\ErrorLog;

/**
 * TODO: PHPDoc.
 */
class ListFormat implements Format {
	use TransformBase;
	
	protected $functions = [];
	protected $itemFormat = null;
	
	public function __construct(Format $itemFormat = null) {
		if ($itemFormat) $this->itemFormat = $itemFormat;
	}
	
	public function setItems(Format $format) {		
		$this->itemFormat = $format;
		return $this;
	}
	
	public function hasLength($length) {
		$this->functions[] = function ($value, & $errors, $path) use ($length) {
			if (\count($value) < $length) {
				$noun = $length == 1 ? 'item' : 'items';
				$errors[] = [$path, "Please provide a list with exactly $length $noun."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	public function hasLengthMin($length) {
		$this->functions[] = function ($value, & $errors, $path) use ($length) {
			if (\count($value) < $length) {
				$noun = $length == 1 ? 'item' : 'items';
				$errors[] = [$path, "Please provide a list with at least $length $noun."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	public function hasLengthMax($length) {
		$this->functions[] = function ($value, & $errors, $path) use ($length) {
			if (\count($value) > $length) {
				$noun = $length == 1 ? 'item' : 'items';
				$errors[] = [$path, "Please provide a list with at most $length $noun."];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
	
	/**
	 * A convenience combination of hasLengthMin() and hasLengthMax().
	 * 
	 * @param int $min
	 * 
	 * @param int $max
	 * 
	 * @return self
	 */
	public function hasLengthInRange($min, $max) {
		$this->hasLengthMin($min);
		$this->hasLengthMax($max);
		
		return $this;
	}
	
	public function isEmpty() {
		$this->functions[] = function ($value, & $errors, $path) {
			if (\count($value) == 0) {
				// A list with isEmpty() test will typically be 1st item in a UnionFormat, where this message will be
				// never seen.
				$errors[] = [$path, "Please provide an empty list."];
				return false;
			} else {
				return true;
			}
		};
		
		return $this;
	}
	
	public function isNotEmpty() {
		$this->functions[] = function ($value, & $errors, $path) {
			if (\count($value) == 0) {
				$errors[] = [$path, "Please provide a list with one or more items."];
				return false;
			} else {
				return true;
			}
		};
		
		return $this;
	}
	
	public function apply($value, ErrorLog $log, $path = null) {
		if (!\is_array($value)) {
			if ($value instanceof ValueBox) return $this->apply($value->getValue(), $log, $path);
			
			$log->error($path, 'Please provide a list.');
			return null;
		}
		
		$errors = null;
		$tempLog = new TempLog($errors);
		$itemFormat = $this->itemFormat;
		$filtered = [];
				
		// We require a sequential, zero based list, anything else is ignored. The PHP array order of the keys in the
		// input, however is irrelevant (the return value will be sorted by key order anyway).
		for ($i = 0;; $i++) {
			if (\key_exists($i, $value)) {
				$filtered[$i] = $itemFormat 
					? $itemFormat->apply($value[$i], $tempLog, $path === null ? $i : $path . '.' . $i) 
					: $value[$i];
			} else {
				break;
			}
		}
		
		if ($errors) {
			$this->importErrors($log, $errors);
			return null;
		} else {
			if ($this->functions) {
				return $this->applyFunctions($this->functions, $value, $log, $path);
			} else {
				return $value;
			}
		}
	}
}