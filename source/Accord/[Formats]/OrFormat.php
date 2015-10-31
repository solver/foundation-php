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

use Solver\Logging\StatusLog as SL;
use Solver\Accord\ActionUtils as AU;
use Solver\Accord\InternalTransformUtils as ITU;

/**
 * Tests the given formats in sequence, until one of them successfully validates the value. You get back the output from
 * that first success. Each next format will get the value unfiltered, without seeing errors from the previous formats.
 * 
 * This format represents a so-called "union type" or "sum type". See also AndFormat and VariantFormat.
 * 
 * The format has a great flexibility that comes with some weaknesses:
 * 
 * - Can be slow with many formats: every format has to run and invalidate, before going to the next.
 * - The errors can be unhelpful and misleading: if all subformats invalidate, the log will ultimately end up with the 
 * errors from the last attemped format. Call useError() to replace those with a custom error message.
 * - It's possible to create an ambiguous union with overlaps, where the wrong format is selected and validated.
 * 
 * For these reasons unions are optimal for simple values (like scalars), while for dictionaries, it's always preferable
 * to use the more explicit and performant VariantFormat where applicable. VariantFormat implements a "tagged union".
 */
class OrFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	protected $formats = [];
	protected $error = null;
	
	public function __construct(Format ...$formats) {
		if ($formats) $this->formats = $formats;
	}
	
	public function add(Format $format) {
		$this->formats[] = $format;
		return $this;
	}
			
	/**
	 * TODO: Figure out a better way to design this method. Also it no longer is in sync that we have non-error events.
	 * 
	 * Calling this method sets an error that will be reported for the format if it fails, *instead* of the events of
	 * the last sub-format that has failed.
	 * 
	 * The path of the error will be the one passed to apply()/fastApply().
	 * 
	 * @param \Solver\Lab\Format $error
	 * null | dict... Pass null to revert back to the default behavior if you have previously set an error.
	 * - message?: null|string;
	 * - code?: null|string;
	 * - details?: null|dict;
	 * 
	 * @return $this
	 */
	public function useError($error) {
		$error['type'] = 'error';
		$this->error = $error;
		return $this;
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		foreach ($this->formats as $i => $format) {
			$subEvents = [];
			
			if ($format instanceof FastAction) {
				$success = $format->fastApply($input, $output, $mask, $subEvents, $path);
			} else {
				$success = AU::emulateFastApply($format, $input, $output, $mask, $subEvents, $path);
			}
			
			if ($success) {
				ITU::mergeTo($events, $subEvents);
				return true;
			}
		}
		
		if ($this->error) {
			if ($mask & SL::ERROR_FLAG) {
				$error = $this->error;
				if ($path && isset($error['path'])) $error['path'] = array_merge($path, $error['path']);
				$events[] = $error;
			}
		} else {
			ITU::mergeTo($events, $subEvents);
		}
		
		return false;
	}
}