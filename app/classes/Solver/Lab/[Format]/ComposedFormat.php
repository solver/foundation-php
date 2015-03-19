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
 * Takes multiple formats and runs the value through each of them in order, feeding the output of one as the input of
 * the next one. 
 * 
 * The processing chain is broken if the value is tested invalid by any of the formats in the chain.
 * 
 * The name of this class refers to functional composition, not to be confused with OOP-style composition. For the
 * latter see VariantFormat, UnionFormat, IntersectFormat, and DictFormat (which is composed of format-driven fields).
 */
class ComposedFormat extends AbstractFormat implements Format {
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
		$tempLog = new SimpleErrorLog();
				
		foreach ($this->formats as $i => $format) {
			$value = $format->extract($value, $tempLog, $path);
			if ($tempLog->hasErrors()) break;
		}
		
		if ($tempLog->hasErrors()) {
			// We can't rely on import() as it's not on the ErrorLog interface (refactoring may fix that).
			foreach ($tempLog->getAllEvents() as $event) {
				$log->addError($event['path'], $event['message'], $event['code'], $event['details']);
			}
		}
		
		return parent::extract($value, $log, $path);
	}
}