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
 * Returns a PHP boolean value if the input can be interpreted as boolean.
 * 
 * Valid input to produce boolean false:
 * 
 * - (bool) false
 * - (int) 0
 * - (float) 0.0
 * - (string) "", "0", "false" (case sensitive)
 * 
 * Valid input to produce boolean true:
 * 
 * - (bool) true
 * - (int) 1
 * - (float) 1.0
 * - (string) "1", "true" (case sensitive)
 * 
 * TODO: PHPDoc.
 */
class BoolFormat extends AbstractFormat implements Format {	
	public function extract($value, ErrorLog $log, $path = null) {
		
		switch (\gettype($value)) {
			case 'boolean':
				break;
			
			case 'integer':
			case 'double':
				if ($value == 0) {
					$value = false;
					break;
				}
				
				if ($value == 1) {
					$value = true;
					break;
				}
				
				$this->errorBadBool($log, $path);
				return null;
				
			case 'string':
				if ($value === '' || $value === '0' || $value === 'false') {
					$value = false;
					break;
				}
				
				if ($value === '1' || $value === 'true') {
					$value = true;
					break;
				}
				
				$this->errorBadBool($log, $path);
				return null;
			
			default:
				$this->errorBadBool($log, $path);
				return null;
		}
		
		return parent::extract($value, $log, $path);
	}
	
	protected function errorBadBool(ErrorLog $log, $path) {
		$log->addError($path, 'Please provide a valid boolean.');
	}
}