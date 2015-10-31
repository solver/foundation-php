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

use Solver\Logging\StatusLog as SL;
use Solver\Accord\InternalTransformUtils as ITU;
use Solver\Toolbox\StringUtils;

/**
 * Returns an email address string if the input can be interpreted as such.
 * 
 * Whitespace is automatically trimmed.
 */
class EmailAddressFormat implements Format, FastAction {	
	use ApplyViaFastApply;
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		if (!\is_string($input)) {			
			if ($input instanceof ToValue) {
				return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			} else {
				goto badEmail;
			}
		}
		
		$output = StringUtils::trim($input);
		
		// TODO: This is RFC 5322 check, verify it's RFC 6530 compliant: http://en.wikipedia.org/wiki/Email_address#Internationalization
		if ($output === '' || (\strlen($output) > 320 || !\preg_match('/^[\w%.\'!#$&*+\/=?^`{|}~-]{1,64}@(?:(?:\d+\.){3}\d+|(?:[a-z\d][a-z\d-]+)+(?:\.[a-z\d][a-z\d-]+)+)$/iD', $output))) {
			goto badEmail;
		}
		
		return true;
		
		badEmail:
			
		if ($mask && SL::ERROR_FLAG) ITU::addErrorTo($events, $path, 'Please fill in a valid email address.');
		$output = null; 
		return null;
	}
}