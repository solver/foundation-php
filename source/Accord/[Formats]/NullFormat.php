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

use Solver\Logging\StatusLog as SL;
use Solver\Accord\InternalTransformUtils as ITU;

/**
 * This format validates only if the value is null.
 * 
 * This is useful in combination with OrFormat, in order to create values which can optionally be null.
 * 
 * TODO: Consider removing this?
 */
class NullFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		if ($input !== null) {
			if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
			// If added first in a OrFormat, this awkward message won't be seen.
			if ($mask & SL::ERROR_FLAG) ITU::errorTo($events, $path, 'Please provide a null value.');
			$output = null;
			return true;
		} else {
			$output = null;
			return false;
		}
	}
}