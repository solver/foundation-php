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

use Solver\Logging\ErrorLog;

/**
 * Extracts the value from each given sub-format (via add()) and combines the result into a single output.
 * 
 * If any of the sub-formats returns errors (doesn't validate) the return value will be null and you get a log with
 * the combined errors from all sub-formats.
 * 
 * Currently, the output of every sub-format should be a dictionary, and the keys between the sub-formats should not
 * overlap.
 * 
 * TODO: Allow scalars where all sub-formats must have the same output and no errors for the result to be valid?
 * TODO: Allow arrays with overlapping values as long as the values are the same?
 * TODO: Add useError() like UnionFormat.
 */
class IntersectFormat implements Format {
	use TransformBase;
	
	/**
	 * @var Format[]
	 */
	protected $formats;
	
	public function __construct(Format ...$formats) {
		if ($formats) $this->formats = $formats;
	}
	
	public function add(Format $format) {
		$this->formats[] = $format;
		return $this;
	}
	
	public function apply($value, ErrorLog $log, $path = null) {
		$formatMaxIndex = \count($this->formats) - 1;
		
		$subValues = [];
		
		foreach ($this->formats as $i => $format) {
			$errors = null;
			$tempLog = new TempLog($errors);
			$subValue = $format->apply($value, $tempLog, $path);
			
			if ($errors) {
				$this->importErrors($log, $errors);
				$errors = [];
			} else {
				if (is_array($subValue)) {
					$subValues[$i] = $subValue;
				} else {
					if ($subValue instanceof ValueBox) $subValue = $subValue->getValue();
					
					if (is_array($subValue)) {
						$subValues[$i] = $subValue;
					} else {
						throw new \Exception('Subformats in an IntersectFormat should return arrays (subformat at index ' . $i . ').');
					}
				}
			}
		}
		
		if ($errors) return null;
		
		// TODO: Add validation checks for non-overlapping keys?
		$value = [];
		foreach ($subValues as $subValue) $value += $subValue;
		
		return $value;
	}
}