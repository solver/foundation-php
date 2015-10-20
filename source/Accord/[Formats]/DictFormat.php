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
 * TODO: Add subset($dictFormat, ...$fields) or similar: an ability to create a partial clone only of selected fields.
 */
class DictFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	protected $fields = [];
	protected $rest = false;
	protected $restFormat = null;
	
	/**
	 * @return self
	 */
	public function addRequired($name, Format $format = null) {
		$this->fields[] = [$name, $format, 0]; 
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function addOptional($name, Format $format = null) {
		$this->fields[] = [$name, $format, 1]; 
		return $this;
	}
	
	/**
	 * Same as addOptional(), but if the field is missing it'll be created, and the default value will be assigned.
	 * 
	 * @return self
	 */
	public function addDefault($name, $default = null, Format $format = null) {
		$this->fields[] = [$name, $format, 2, $default];
		return $this;
	}
	
	/**
	 * Allows keys which are specified neither as required nor optional to be extracted. Be careful with this option,
	 * this means you have no control over which keys end up in your filtered data.
	 * 
	 * TODO: Add bool flag (false by default) which allows the given format to receive tuple [$key, $val] instead of just $val.
	 * Requires consideration about how to interpret the error message paths coming back from that tuple into the dict.
	 * 
	 * return $this
	 */
	public function addRest(Format $format = null) {
		if ($this->rest) throw new \Exception('Method addRest() can only be called once per DictFormat instance.');
		$this->rest = true;
		$this->restFormat = $format;
		return $this;
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		static $TYPE_REQUIRED = 0;
		static $TYPE_OPTIONAL = 1;
		static $TYPE_DEFAULT = 2;
		
		if (!\is_array($input)) {
			if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
			if ($mask & SL::ERROR_FLAG) ITU::errorTo($events, $path, 'Please provide a dict.');
			$output = null;
			return null;
		}
		
		$success = true;
		$output = [];
		
		foreach ($this->fields as $field) {
			/* @var $field Format */
			list($name, $format, $type) = $field;
			
			// Isset is faster so we try that and fall back to key_exists for null values. 
			// TODO: Should we just ignore nulls? Or make it an option?
			$exists = isset($input[$name]) || \key_exists($name, $input);
			
			if ($exists) {
				$subInput = $input[$name];
				unset($input[$name]);
				
				if ($format) {
					$subPath = $path;
					$subPath[] = $name;
					
					if ($format instanceof FastAction) {
						$success = $success && $format->fastApply($subInput, $output[$name], $mask, $events, $subPath);
					} else {
						$success = $success && AU::emulateFastApply($format, $subInput, $output[$name], $mask, $events, $subPath);
					}
				} else {
					$output[$name] = $subInput;
				}
			} else {
				switch ($type) {
					case $TYPE_REQUIRED:
						// Missing fields are a dict-level error (don't add $name to the $path in this case).
						if ($mask & SL::ERROR_FLAG) ITU::errorTo($events, $path, 'Please provide required field "' . $name . '".');
						$success = false;
						break;
						
					case $TYPE_DEFAULT:
						$output[$name] = $field[3];
						break;
				}
			}
		}
		
		if ($this->rest) {
			if ($this->restFormat) {
				if ($format instanceof FastAction) {
					foreach ($input as $subName => $subInput) {
						$subPath = $path;
						$subPath[] = $subName;
						$success = $success && $format->fastApply($subInput, $output[$subName], $mask, $events, $subPath);
					}
				} else {
					foreach ($input as $subName => $subInput) {
						$subPath = $path;
						$subPath[] = $subName;
						$success = $success && AU::emulateFastApply($format, $subInput, $output[$subName], $mask, $events, $subPath);
					}
				}
			} else {
				$output += $input;
			}
		} else {
			if ($mask & SL::WARNING_FLAG) ITU::warningTo($events, $path, 'One or more fields were ignored: "' . implode('", "', array_keys($input)) . '".');
		}
		
		if ($success) {
			return true;
		} else {
			$output = null;
			return false;
		}
	}
}