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

use Solver\Logging\ErrorLog;

/**
 * TODO: Add subset($dictFormat, ...$fields) or similar: an ability to create a partial clone only of selected fields.
 */
class DictFormat implements Format {
	use TransformBase;
	
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
	
	public function apply($value, ErrorLog $log, $path = null) {
		static $TYPE_REQUIRED = 0;
		static $TYPE_OPTIONAL = 1;
		static $TYPE_DEFAULT = 2;
		
		if (!\is_array($value)) {
			$log->error($path, 'Please provide a dict.');
			return null;
		}
		
		$selected = [];
		$tempLog = new TempLog($errors);
		
		foreach ($this->fields as $field) {
			list($name, $format, $type) = $field;
			$exists = \key_exists($name, $value);
			
			if ($exists) {
				$fieldValue = $value[$name];
				unset($value[$name]);
				$selected[$name] = $format ? $format->apply($fieldValue, $tempLog, $path === null ? $name : $path . '.' . $name) : $fieldValue;		
			} else {
				switch ($type) {
					case $TYPE_REQUIRED:
						// Missing fields are a dict-level error (don't add $name to the $path in this case).
						$errors[] = [$path, 'Please provide required field "' . $name . '".'];
						break;
					case $TYPE_DEFAULT:
						$selected[$name] = $field[3];
						break;
				}
			}
		}
		
		if ($this->rest) {
			if ($this->restFormat) foreach ($value as $fieldName => $fieldValue) {
				$value[$fieldName] = $format->apply($fieldValue, $tempLog, $path === null ? $fieldName : $path . '.' . $fieldName);
			}
			$value = $selected + $value;
		} else {
			$value = $selected;
		}
		
		if ($errors) {
			$this->importErrors($log, $errors);
			return null;
		} else {
			return $value;
		}
	}
}