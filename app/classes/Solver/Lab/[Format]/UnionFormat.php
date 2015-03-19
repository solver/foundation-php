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
 * Tests the given formats in sequence, until one of them successfully validates the value. You get back the output from
 * that first success. Each next format will get the value unfiltered, without seeing errors from the previous formats.
 * 
 * Union formats have great flexibility that comes with some weaknesses:
 * 
 * - Can be slow with many formats: every format has to run and invalidate, before going to the next.
 * - The errors can be unhelpful and misleading: if all subformats invalidate, the log will ultimately end up with the 
 * errors from the last attemped format. Call useError() to replace those with a custom error message.
 * - It's possible to create an ambiguous union with overlaps, where the wrong format is selected and validated.
 * 
 * For these reasons unions are optimal for simple values (like scalars), while for dictionaries, it's always preferable
 * to use the more explicit and performant VariantFormat where applicable. VariantFormat implements a "tagged union".
 */
class UnionFormat extends AbstractFormat implements Format {	
	protected $formats = [];
	protected $error = null;
			
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
			
	/**
	 * Calling this method sets an error that will be reported for the format if it fails, *instead* of the errors of
	 * the last sub-format that has failed.
	 * 
	 * The path of the error will be the one passed to extract().
	 * 
	 * @param \Solver\Lab\Format $error
	 * null | dict... Pass null to revert back to the default behavior if you have previously set an error.
	 * - message?: null|string;
	 * - code?: null|string;
	 * - details?: null|dict;
	 * 
	 * @return self
	 */
	public function useError($error) {
		$this->error = $error;
		return $this;
	}
	
	/**
	 * TODO: Old name for add(). Remove when no longer used.
	 *
	 * @deprecated
	 * 
	 * @param \Solver\Lab\Format $format
	 * 
	 * @return self
	 */
	public function attempt(Format $format) {
		return $this->add($format);
	}
	
	public function extract($value, ErrorLog $log, $path = null) {
		$formatMaxIndex = \count($this->formats) - 1;
		
		foreach ($this->formats as $i => $format) {
			$subLog = new SimpleErrorLog();
			$subValue = $format->extract($value, $subLog, $path);
			
			if ($subLog->hasErrors()) {
				if ($i == $formatMaxIndex) {
					if ($this->error) {
						$error = $this->error + ['message' => null, 'code' => null, 'details' => null];
						$log->addError($path, $error['message'], $error['code'], $error['details']);
					} else {
						// We can't rely on import() as it's not on the ErrorLog interface (refactoring may fix that).
						foreach ($subLog->getAllEvents() as $event) {
							$log->addError($event['path'], $event['message'], $event['code'], $event['details']);
						}
					}
					
					return null;
				}
			} else {
				$value = $subValue;
				break;
			}
		}
		
		return parent::extract($value, $log, $path);
	}
}