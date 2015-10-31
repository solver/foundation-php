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

use Solver\Logging\StatusLog as SL;
use Solver\Accord\ActionUtils as AU;
use Solver\Accord\InternalTransformUtils as ITU;

/**
 * TODO: PHPDoc.
 * TODO: Add warning for skipped "rest" indexes (like in DictFormat).
 */
class ListFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
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
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($length) {
			if (\count($input) < $length) {
				$noun = $length == 1 ? 'item' : 'items';
				if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, "Please provide a list with exactly $length $noun.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	public function hasLengthMin($length) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($length) {
			if (\count($input) < $length) {
				$noun = $length == 1 ? 'item' : 'items';
				if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, "Please provide a list with at least $length $noun.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	public function hasLengthMax($length) {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) use ($length) {
			if (\count($input) > $length) {
				$noun = $length == 1 ? 'item' : 'items';
				if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, "Please provide a list with at most $length $noun.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
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
	 * @return $this
	 */
	public function hasLengthInRange($min, $max) {
		$this->hasLengthMin($min);
		$this->hasLengthMax($max);
		
		return $this;
	}
	
	public function isEmpty() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			if (\count($input) == 0) {
				// A list with isEmpty() test will typically be 1st item in a OrFormat, where this message will be
				// never seen.
				if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, "Please provide an empty list.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	public function isNotEmpty() {
		$this->functions[] = static function ($input, & $output, $mask, & $events, $path) {
			if (\count($input) == 0) {
				if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, "Please provide a list with one or more items.");
				$output = null;
				return false;
			} else {
				$output = $input;
				return true;
			}
		};
		
		return $this;
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		if (!\is_array($input)) {
			if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
			if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, 'Please provide a list.');
			$output = null;
			return null;
		}
		
		$itemFormat = $this->itemFormat;
		$output = [];
		$success = false;
				
		// We require a sequential, zero based list, anything else is ignored. The PHP array order of the keys in the
		// input, however is irrelevant (the return value will be sorted by key order anyway).
		for ($i = 0;; $i++) {
			// We isset and fall back to key_exists (slower) for null checks.
			// TODO: Option to ignore null item values?
			if (isset($input[$i]) || \key_exists($i, $input)) {
				if ($itemFormat) {
					$itemPath = $path;
					$itemPath[] = $i;
					if ($itemFormat instanceof FastAction) {
						$success = $success && $itemFormat->fastApply($input, $output, $mask, $events, $itemPath);
					} else {
						$success = $success && AU::emulateFastApply($itemFormat, $input, $output, $mask, $events, $itemPath);
					}
				} else {
					$output[$i] = $input[$i];
				}
			} else {
				break;
			}
		}
		
		if ($success) {
			if ($this->functions) {
				return ITU::fastApplyFunctions($this->functions, $output, $output, $mask, $events, $path);
			} else {
				return true;
			}
		} else {
			$output = null;
			return false;
		}
	}
}