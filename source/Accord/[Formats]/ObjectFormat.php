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
 * If the input value is a PHP object (of any kind) it's returned unmodified.
 * 
 * TODO: Consider isNotInstanceOf, isInstanceOfAll, isInstanceOfAny variations etc.
 */
class ObjectFormat implements Format {
	use TransformBase;
	
	protected $functions = [];
	
	public function apply($value, ErrorLog $log, $path = null) {
		if (!\is_object($value)) {
			$log->error($path, 'Please provide a valid object.');
			return null;
		}	
	
		if ($this->functions) {
			return $this->applyFunctions($this->functions, $value, $log, $path);
		} else {
			return $value;
		}
	}
	
	public function isInstanceOf($className) {
		$this->functions[] = static function ($value, & $errors, $path) use ($className) {
			if (!$value instanceof $className) {
				$errors[] = [$path, 'Please provide an object instance of ' . $className. '.'];
				return null;
			} else {
				return $value;
			}
		};
		
		return $this;
	}
}