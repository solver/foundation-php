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
 * A variant is an implementation of a "tagged union" type, for dictionaries where a specific field is designated to 
 * differentiate between the subtypes in a union.
 * 
 * Variants are able to directly select the correct subformat by matching the provided values for the tag field. This
 * results in better performance and improved error reporting (the errors from the correct subtype get reported if the
 * input invalidates) compared to untagged unions, but it has a limitation: it requires subformats to return a
 * collection (PHP array) so the tag field can be addressed by name (dict), or index (list, tuple). OrFormat
 * implements an untagged union and has no limitation on the value format.
 * 
 * TODO: Allow one subformat with no tagged value (i.e. the lack of a tag selects that subformat), thus making the tag
 * field optional (it's by default required).
 */
class VariantFormat implements Format, FastAction {	
	use ApplyViaFastApply;
	
	protected $tagFieldName = null;
	protected $mergeTagInOutput;
	protected $formats = [];
	
	/**
	 * @param string|int $fieldName
	 * The field name/index that will be used as the "type tag": its value will be used to identify which of the 
	 * possible variant formats should be selected for the input data.
	 * 
	 * @param bool $mergeInOutput
	 * False if you don't want the tag to be merged into the output (the separate sub-formats may still read that field
	 * selectively). True if you want to merge the tag field into the output (without adding it to each sub-format).
	 * 
	 * Note: the merged tag may be overwritten by a sub-format if it returns the same field name (sub-formats have 
	 * higher priority).
	 * 
	 * @return self
	 */
	public function setTag($fieldName, $mergeInOutput) {
		// For now we enforce this to ensure a consistent method call order, but in the future we may drop this requirement.
		if ($this->formats) throw new \Exception('You must call method setTagFieldName() before adding formats via add().');
		
		$this->tagFieldName = $fieldName;
		$this->mergeTagInOutput = $mergeInOutput;
		
		return $this;
	}
	
	/**
	 * @param string|int $tagValue
	 * Set a unique string/number value that identifies this format. The tag MUST be a scalar value. Tag values are 
	 * compared like strings (basically), so integer 0 and string "0" represent the same tag value.
	 *  
	 * @param Format $format
	 * @throws \Exception
	 * @return \Solver\Lab\OrFormat
	 */
	public function add($tagValue, Format $format) {
		if (!is_scalar($tagValue)) throw new \Exception('Tag values must be scalar (integer, int, or a float with an integer value).');
		if (isset($this->formats[$tagValue])) throw new \Exception('Tag value "' . $tagValue . '" has already been specified for another format in this variant.');
		
		$this->formats[$tagValue] = $format;
		return $this;
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		$tagField = $this->tagFieldName;
		
		if ($tagField === null) {
			// Developer mistake, misconfigured format.
			throw new \Exception('You must set the tag field name via tag() before you extract.');
		}
		
		// Variants must be collections (dict, list, tuple).
		if (!is_array($input)) {
			if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
			// TODO: More specific error?
			if ($mask & SL::ERROR_FLAG) ITU::errorTo($events, $path, 'Please fill in a valid value.');
			$output = null;
			return false;
		}
		
		// The tag field must be present.
		if (!key_exists($tagField, $input)) {
			if ($mask & SL::ERROR_FLAG) {
				if ((string) $tagField === (string) (int) $tagField) {
					$message = 'Please provide required index "' . $tagField . '".';
				} else {
					$message = 'Please provide required field "' . $tagField . '".';
				}
				
				ITU::errorTo($events, $path, $message);
			}
			
			$output = null;
			return false;
		}
		
		// The value must match our registered type tags.
		if (!isset($this->formats[$input[$tagField]])) {
			if ($mask & SL::ERROR_FLAG) {
				$subPath = $path;
				$subPath[] = $this->tagFieldName;
				
				// TODO: More specific error?
				ITU::errorTo($events, $subPath, 'Please fill in a valid value.');
			}
			
			$output = null;
			return false;
		}
		
		$format = $this->formats[$input[$tagField]];
		
		if ($format instanceof FastAction) {
			$success = $format->fastApply($input, $output, $mask, $events, $path);
		} else {
			$success = AU::emulateFastApply($format, $input, $output, $mask, $events, $path);
		}
		
		if ($success) {
			// TODO: Check if we're overriding existing field and error if not identical value?
			if ($this->mergeTagInOutput) $output[$this->tagFieldName] = $input[$this->tagFieldName];
			return true;
		} else {
			return false;
		}
	}
}