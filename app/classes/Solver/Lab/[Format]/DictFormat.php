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
namespace Solver\Lab;

/**
 * TODO: PHPDoc.
 */
class DictFormat extends AbstractFormat {
	protected $fields = [];
	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_array($value)) {
			$log->addError($path, 'Please provide a dict.');
			return null;
		}
		
		$filtered = [];
		$errorCount = $log->getErrorCount();
		
		foreach ($this->fields as $field) {
			list($name, $format, $required) = $field;
			
			if (\key_exists($name, $value)) {
				$filtered[$name] = $format ? $format->extract($value[$name], $log, $path === null ? $name : $path . '.' . $name) : $value[$name];
			} 
			
			else if ($required) {
				// Boolean auto-promotion: in HTTP an unchecked checkbox is not submitted at all, to reduce friction we
				// autocreate required (and only required) fields of BoolFormat, with default value false.
				// TODO: This behavior should be optional.
				if ($format instanceof BoolFormat) {
					$filtered[$name] = $format->extract(false, $log, $path === null ? $name : $path . '.' . $name);
				} 
				
				// Dict/list auto-promotion: for the same reasons as above, the PHP way of encoding dicts/lists for HTTP
				// fields provide no way of passing an empty array, so if a required dict/list field is missing, we 
				// create an empty one.
				// TODO: This behavior should be optional.
				else if ($format instanceof DictFormat || $format instanceof ListFormat) {
					$filtered[$name] = $format->extract([], $log, $path === null ? $name : $path . '.' . $name);
				}
				
				else {
					// Missing fields are a dict-level error (don't add $name to the $path in this case).
					$log->addError($path, 'Please provide required field "' . $name . '".');
				}
			}
		}
		
		$value = $filtered; 
		
		if ($log->getErrorCount() > $errorCount) {
			return null;
		} else {
			return parent::extract($value, $log, $path);
		}
	}
	
	/**
	 * @param string $name
	 * @param \Solver\Lab\Format $format
	 * @return self
	 */
	public function required($name, Format $format = null) {
		if ($this->rules) throw new \Exception('You should call method required() before any test*() or filter*() calls.');
		
		$this->fields[] = [$name, $format, true]; 
		
		return $this;
	}
	
	/**
	 * @param string $name
	 * @param \Solver\Lab\Format $format
	 * @return self
	 */
	public function optional($name, Format $format = null) {
		if ($this->rules) throw new \Exception('You should call method optional() before any test*() or filter*() calls.');
		
		$this->fields[] = [$name, $format, true]; 
		
		return $this;
	}
}