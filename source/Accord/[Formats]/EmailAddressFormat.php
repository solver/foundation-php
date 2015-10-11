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

use Solver\Logging\ErrorLog;
use Solver\Toolbox\StringUtils;

/**
 * Returns an email address string if the input can be interpreted as such.
 * 
 * Whitespace is automatically trimmed.
 */
class EmailAddressFormat implements Format {	
	public function apply($value, ErrorLog $log, $path = null) {
		if (!\is_string($value)) goto badEmail;
		
		$value = StringUtils::trim($value);
		
		// TODO: This is RFC 5322 check, verify it's RFC 6530 compliant: http://en.wikipedia.org/wiki/Email_address#Internationalization
		if ($value === '' || (\strlen($value) > 320 || !\preg_match('/^[\w%.\'!#$&*+\/=?^`{|}~-]{1,64}@(?:(?:\d+\.){3}\d+|(?:[a-z\d][a-z\d-]+)+(?:\.[a-z\d][a-z\d-]+)+)$/iD', $value))) {
			goto badEmail;
		}
		
		return $value;
		
		badEmail:
		if ($value instanceof ValueBox) return $this->apply($value->getValue(), $log, $path);
			
		$log->error($path, 'Please fill in a valid email address.');
		return null;
	}
}