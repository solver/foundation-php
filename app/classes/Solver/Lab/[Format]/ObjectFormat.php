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
namespace Solver\Lab;

/**
 * If the input value is a PHP object (of any kind) it's returned unmodified.
 * 
 * TODO: This class will eventually be extended with more specific tests, like testInstanceOf(...$types).
 */
class ObjectFormat extends AbstractFormat implements Format {	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!is_object($value)) {
			$log->addError($path, 'Please provide a valid object.');
			return null;
		}
		
		return parent::extract($value, $log, $path);
	}
}