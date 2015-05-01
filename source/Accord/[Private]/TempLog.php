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

use Solver\Logging\LogException;
use Solver\Logging\ErrorLog;

/**
 * DO NOT USE. For internal consumption by the Solver\Accord library only.
 */
class TempLog implements ErrorLog {
	protected $errors;

	/**
	 * @param array $errors
	 * list<#ErrorTuple>; List by reference where the errors will be stored. The reference allows Transform and Format
	 * instances to efficiently query and reset temp logs for quick reuse (a performance optimization).
	 * 
	 * #ErrorTuple: tuple... We use an intermediate tuple-based internal format for events, to ease directly pushing
	 * to the list of $errors via the refence.
	 * - path: null|string;
	 * - message: null|string;
	 * - code?: null|string;
	 * - details?: null|dict;
	 */
	public function __construct(& $errors) {
		if ($errors === null) $errors = [];
		$this->errors = & $errors;
	}
	
	/* (non-PHPdoc)
	 * @see \Solver\Logging\ErrorLog::error()
	 */
	public function error($path, $message, $code = null, array $details = null) {
		if ($message === null && $code === null) LogException::throwNullMessageAndCode();
		$this->errors[] = [$path, $message, $code, $details];
	}

	/* (non-PHPdoc)
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array $event) {
		if ($event['type'] !== 'error') LogException::throwBadFormat($type);
		$this->errors[] = [$event['path'], $event['message'], $event['code'], $event['details']];
	}
}