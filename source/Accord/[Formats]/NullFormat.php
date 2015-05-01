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
 * This format validates only if the value is null.
 * 
 * This is useful in combination with UnionFormat, in order to create values which can optionally be null.
 */
class NullFormat implements Format {
	public function apply($value, ErrorLog $log, $path = null) {
		if ($value !== null) {
			// If added first in a UnionFormat, this awkward message won't be seen.
			$log->error($path, 'Please provide a null value.');
			return null;
		} else {
			return $value;
		}
	}
}