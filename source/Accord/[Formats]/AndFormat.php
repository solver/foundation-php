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

use Solver\Accord\ActionUtils as AU;

/**
 * Extracts the value from each given sub-format (via add()) and combines the result into a single output. 
 * 
 * This format represents a so-called "intersection type" or "product type". See also OrFormat and VariantFormat.
 * 
 * If any of the sub-formats returns errors (doesn't validate) the return value will be null and you get a log with
 * the combined errors from all sub-formats.
 * 
 * Currently, the output of every sub-format should be a dictionary, and the keys between the sub-formats should not
 * overlap.
 * 
 * TODO: Allow scalars where all sub-formats must have the same output and no errors for the result to be valid?
 * TODO: Allow arrays with overlapping values as long as the values are the same?
 * TODO: Add useError() like OrFormat.
 */
class AndFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
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
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		$formatMaxIndex = \count($this->formats) - 1;
		
		$output = [];
		$success = true;
		
		foreach ($this->formats as $i => $format) {
			$subOutput = null;
			
			if ($format instanceof FastAction) {
				$subSuccess = $format->fastApply($input, $subOutput, $mask, $events, $path);
			} else {
				$subSuccess = AU::emulateFastApply($format, $input, $subOutput, $mask, $events, $path);
			}
			
			if ($subSuccess) {
				if (is_array($subOutput)) {
					// TODO: Add validation checks for non-overlapping keys?
					$output += $subOutput;
				} else {
					if ($subOutput instanceof ToValue) $subOutput = $subOutput->toValue();
					
					if (is_array($subOutput)) {
						// TODO: Add validation checks for non-overlapping keys?
						$output += $subOutput;
					} else {
						// Considered a dev mistake so we throw root Exception instead of consider it an action error.
						throw new \Exception('Subformats in an AndFormat should return arrays (subformat at index ' . $i . ').');
					}
				}
			}
			
			$success = $success && $subSuccess;
		}
		
		if ($success) {
			return true;
		} else {
			$output = null;
			return false;
		}
	}
}