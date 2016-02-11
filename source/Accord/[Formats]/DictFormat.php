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
 * TODO: Add ignoreRest() /no warnings on rest/ rejectRest() /errors on rest/ to complement addRest() and we need a
 * method for the default behavior warnOnRest() or similar.
 * TODO: Add a format for requiring alternate fields (x, y or a, b), a specific case of OrFormat. We have this in
 * Sidekick fields. A DictFormat-specific union with alternate fields. ->requireEither(['foo', 'bar'], ['baz']).
 */
class DictFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	// TODO: These should become protected constants in PHP 7.1.
	protected static $REST_IGNORE = 0;
	protected static $REST_ADD = 1;
	protected static $REST_WARN = 2;
	protected static $REST_ERROR = 3;
	
	protected $fields = [];
	protected $rest = 0; // One of the REST_* constants.
	protected $restFormat = null;
	protected $preserveNull = false;
	
	/**
	 * @return $this
	 */
	public function addRequired($name, Format $format = null) {
		$this->fields[] = [$name, $format, 0]; 
		return $this;
	}
	
	/**
	 * @return $this
	 */
	public function addOptional($name, Format $format = null) {
		$this->fields[] = [$name, $format, 1]; 
		return $this;
	}
	
	/**
	 * Same as addOptional(), but if the field is missing it'll be created in output & assigned the given default value.
	 * 
	 * @return $this
	 */
	public function addDefault($name, $default = null, Format $format = null) {
		$this->fields[] = [$name, $format, 2, $default];
		return $this;
	}
	
	/**
	 * If the format encounters additional fields over those which are explicitly specified, it adds them to the output,
	 * optionally filtered through a given format.
	 * 
	 * Be careful with this option, this means you have no control over which keys end up in your output.
	 * 
	 * TODO: Add bool flag (false by default) which allows the given format to receive tuple [$key, $val] instead of
	 * just $val. Requires consideration about how to interpret the error message paths coming back from that tuple
	 * into the dict.
	 * 
	 * This function overrides the effect of other {verb}Rest() methods.
	 * 
	 * @return $this
	 */
	public function addRest(Format $format = null) {
		$this->rest = self::$REST_ADD;
		$this->restFormat = $format;
		return $this;
	}
	
	/**
	 * If the format encounters additional fields over those which are explicitly specified, it silently ignores them.
	 * 
	 * This is the default behavior if you don't call any of the {verb}Rest() methods.
	 * 
	 * This function overrides the effect of other {verb}Rest() methods.
	 * 
	 * @return $this
	 */
	public function ignoreRest() {
		$this->rest = self::$REST_IGNORE;
		$this->restFormat = null;
		return $this;
	}
	
	/**
	 * If the format encounters additional fields over those which are explicitly specified, it ignores them, but adds
	 * a warning about the ignored keys to the provided log.
	 * 
	 * Keep in mind you'll get false positives when using this mode for dictionaries that participate in algebraic 
	 * expressions like unions (OrFormat) and intersections (AndFormat). It's normal in such expressions for a subformat
	 * to only need a subset of the input.
	 * 
	 * This function overrides the effect of other {verb}Rest() methods.
	 * 
	 * @return $this
	 */
	public function warnRest() {
		$this->rest = self::$REST_WARN;
		$this->restFormat = null;
		return $this;
	}
	
	/**
	 * If the format encounters additional fields over those which are explicitly specified, it fails, adding an error
	 * about the unknown field names to the provided log.
	 * 
	 * Keep in mind you'll get false positives when using this mode for dictionaries that participate in algebraic 
	 * expressions like unions (OrFormat) and intersections (AndFormat). It's normal in such expressions for a subformat
	 * to only need a subset of the input.
	 * 
	 * This function overrides the effect of other {verb}Rest() methods.
	 * 
	 * @return $this
	 */
	public function rejectRest() {
		$this->rest = self::$REST_ERROR;
		$this->restFormat = null;
		return $this;
	}
	
	/**
	 * Whether to preserve null values, or treat them the same as if the key is not set.
	 * 
	 * TODO: We need this in all collection types (List, Tuple, tags in Variant etc.).
	 * 
	 * @param string $preserve
	 * True to preserve (default when calling the method), false to ignore (default for the class if you don't call this
	 * method).
	 * 
	 * @return $this
	 */
	public function preserveNull($preserve = true) {
		$this->preserveNull = $preserve;
		return $this;
	}
	
	// TODO: We could short-circuit here and not run further tests on first failure. We deliberately run everything in
	// full to produce a more full report. Make an option?
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		// TODO: Make protected constants in PHP 7.1.
		static $TYPE_REQUIRED = 0;
		static $TYPE_OPTIONAL = 1;
		static $TYPE_DEFAULT = 2;
		
		if (!\is_array($input)) {
			if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
			if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, 'Please provide a dict.');
			$output = null;
			return null;
		}
		
		$preserveNull = $this->preserveNull;
		
		$success = true;
		$output = [];
		
		foreach ($this->fields as $field) {
			/* @var $field Format */
			list($name, $format, $type) = $field;
			
			// TODO: Optimization opportunities when we don't preserve null and in REST_IGNORE mode, to avoid the
			// key_exists() call.
			if (isset($input[$name])) {
				$exists = true;
			} elseif (\key_exists($name, $input)) {
				if ($preserveNull) {
					$exists = true;
				} else {
					$exists = false;
					unset($input[$name]);
				}
			} else {
				$exists = false;
			}
			
			if ($exists) {
				$subInput = $input[$name];
				unset($input[$name]);
				
				if ($format) {
					$subPath = $path;
					$subPath[] = $name;
					
					// We could short-circuit here if $success if false, we deliberately don't in order to produce more
					// full error reports.
					if ($format instanceof FastAction) {
						$subSuccess = $format->fastApply($subInput, $output[$name], $mask, $events, $subPath);
					} else {
						$subSuccess = AU::emulateFastApply($format, $subInput, $output[$name], $mask, $events, $subPath);
					}
					$success = $success && $subSuccess;
				} else {
					$output[$name] = $subInput;
				}
			} else {
				switch ($type) {
					case $TYPE_REQUIRED:
						// Missing fields are a dict-level error (don't add $name to the $path in this case).
						if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, 'Please provide required field "' . $name . '".');
						$success = false;
						break;
						
					case $TYPE_DEFAULT:
						$output[$name] = $field[3];
						break;
				}
			}
		}
		
		if ($input && $this->rest != self::$REST_IGNORE) {
			switch ($this->rest) {
				case self::$REST_WARN:
					if ($mask & SL::T_WARNING) ITU::addWarningTo($events, $path, 'One or more unexpected fields were ignored: "' . implode('", "', array_keys($input)) . '".');
					break;
				
				case self::$REST_ERROR:
					if ($mask & SL::T_ERROR) ITU::addErrorTo($events, $path, 'Unexpected fields: "' . implode('", "', array_keys($input)) . '".');
					break;
			
				case self::$REST_ADD:
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
					break;
			}
			
		}
		
		if ($success) {
			return true;
		} else {
			$output = null;
			return false;
		}
	}
}