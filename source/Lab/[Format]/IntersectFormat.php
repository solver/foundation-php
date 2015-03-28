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
 * Extracts the value from each given sub-format (via add()) and combines the result into a single output.
 * 
 * If any of the sub-formats returns errors (doesn't validate) the entire value is set to null and you get a log with
 * the combined errors from all sub-formats.
 * 
 * Currently, the output of every sub-format should be a dictionary, and the keys between the sub-formats should not
 * overlap (which is required by sensible type design).
 */
class IntersectFormat extends AbstractFormat implements Format {
	protected $formats;

	/**
	 * @param \Solver\Lab\Format $format
	 * 
	 * @return self
	 */
	public function add(Format $format) {
		if ($this->rules) throw new \Exception('You should call method add() before any test*() or filter*() calls.');
		
		$this->formats[] = $format;
		
		return $this;
	}
	
	public function extract($value, ErrorLog $log, $path = null) {
		$formatMaxIndex = \count($this->formats) - 1;
		
		$values = [];
		
		foreach ($this->formats as $i => $format) {
			$subLog = new SimpleErrorLog();
			$subValue = $format->extract($value, $subLog, $path);
			
			if ($subLog->hasErrors()) {
				// We can't rely on import() as it's not on the ErrorLog interface (refactoring may fix that).
				foreach ($subLog->getAllEvents() as $event) {
					$log->addError($event['path'], $event['message'], $event['code'], $event['details']);
				}
			} else {
				if (is_array($value)) {
					$values[$i] = $subValue;
				} else {
					throw new \Exception('Subformats in an IntersectFormat should return dictionary arrays.');
				}
			}
		}
		
		if ($log->hasErrors()) {
			return null;
		} 
		
		$value = [];
		foreach ($values as $subValue) $value += $subValue;
		
		return parent::extract($value, $log, $path);
	}
}