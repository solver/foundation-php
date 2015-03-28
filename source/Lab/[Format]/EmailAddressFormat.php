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
 * Returns an email address string if the input can be interpreted as such.
 * 
 * Whitespace is automatically trimmed.
 */
class EmailAddressFormat extends StringFormat implements Format {	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_string($value)) {
			$this->errorBadEmail($log, $path);
			return null;
		}
		
		$value = StringUtils::trimWhitespace($value);
		
		// TODO: This is RFC 5322 check, verify it's RFC 6530 compliant: http://en.wikipedia.org/wiki/Email_address#Internationalization
		if ($value === '' || (\strlen($value) > 320 || !\preg_match('/^[\w%.\'!#$&*+\/=?^`{|}~-]{1,64}@(?:(?:\d+\.){3}\d+|(?:[a-z\d][a-z\d-]+)+(?:\.[a-z\d][a-z\d-]+)+)$/iD', $value))) {
			$this->errorBadEmail($log, $path);
			return null;
		}
		
		$value = parent::extract($value, $log, $path);
		
		return $value;
	}
	
	protected function errorBadEmail(ErrorLog $log, $path) {
		$log->addError($path, 'Please fill in a valid email address.');
	}
}