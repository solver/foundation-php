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
 * DO NOT USE. This is an internal implementation detail of Solver\Accord's formats & transforms, and may change or go
 * away without warning.
 */
trait TransformBase {
	/**
	 * @param array $functions
	 * list<#Function>;
	 * 
	 * #Function: ($value: any, & $errors: list<#Error>, $path: null|string) => any;
	 * 
	 * #Error: tuple; A tuple matching a valid argument list for ErrorLog::error()'s method.
	 * 
	 * @param mixed $value
	 * Value to transform.
	 * 
	 * @param \Solver\Accord\ErrorLog $log
	 * Where to log errors.
	 * 
	 * @param null|string $path
	 * Base path for errors.
	 * 
	 * @return null|mixed
	 * Transformed value (or null).
	 */
	protected function applyFunctions($functions, $value, $log, $path) {
		$errors = null;
		$tempLog = new TempLog($errors);
		
		foreach ($functions as $function) {
			$value = $function($value, $errors, $path);
			
			if ($errors) {
				$this->importErrors($log, $errors);
				return null;
			}
		}
		
		return $value;
	}
	
	/**
	 * @param ErrorLog $log
	 * Imports errors here.
	 * 
	 * @param array $errors
	 * list<tuple>; Tuples as utilized by TempLog.
	 */
	protected function importErrors(ErrorLog $log, & $errors) {
		foreach ($errors as $error) $log->error(...$error);
	}
}