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

use Solver\Report\ErrorLog;

/**
 * Takes multiple formats and runs the value through each of them in order, feeding the output of one as the input of
 * the next one. 
 * 
 * The processing chain is interrupted if the value is tested invalid (logged errors) by any of the formats in the chain.
 * 
 * The name of this class refers to functional composition, not to be confused with OOP-style composition. For the
 * latter see VariantFormat, UnionFormat, IntersectFormat, and DictFormat (which is composed of format-driven fields).
 */
class CompositeFormat implements Format {
	use TransformBase;
	
	protected $formats;
	
	public function __construct(Format ...$formats) {
		if ($formats) foreach ($formats as $format) $this->add($format);
	}
			
	/**
	 * Add a new format to the composition.
	 * 
	 * @param Format $format 
	 * @return self
	 */
	public function add(Format $format) {
		$this->formats[] = $format;
		return $this;
	}
	
	public function apply($value, ErrorLog $log, $path = null) {
		$tempLog = new TempLog($errors);
		
		foreach ($this->formats as $i => $format) {
			$value = $format->apply($value, $tempLog, $path);
			if ($errors) break;
		}
		
		if ($errors) {
			$this->importErrors($log, $errors);
			return null;
		} else {
			return $value;
		}
	}
}