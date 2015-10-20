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
namespace Solver\Logging;

/**
 * Covers common error conditions occuring with event logs. Throw this from your logs. Extend and add custom conditions.
 */
class LogException extends \Exception {
	/**
	 * Generic error condition when passing incorrectly formatted event fields.
	 */
	const BAD_FORMAT = 'badFormat';
		
	/**
	 * Logs may actively refuse to process events outside their predefined set of types.
	 */
	const UNKNOWN_TYPE = 'unknownType';
	
	public function __construct($message = null, $code = 0, $previous = null) {
		parent::__construct($message, (int) $code, $previous);
		$this->code = $code; // Allows string codes.
	}
	
	public static function throwBadFormat($message = null) {
		throw new static('Bad event format' . ($message ? ': ' . $message : '') . '.', self::BAD_FORMAT);
	}
		
	public static function throwUnknownType($type) {
		throw new static('Unknown event type "' . $type . '".', self::UNKNOWN_TYPE);
	}
}