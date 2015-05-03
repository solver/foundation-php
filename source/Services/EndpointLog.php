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
namespace Solver\Services;

use Solver\Logging\DefaultErrorMemoryLog;

/**
 * A log that can contain error events, with an ability to throw a EndpointException (containing the log), when there
 * are errors in the log.
 * 
 * Throwing a EndpointException with one or more errors by manipulating a EndpointLog is the standard way for a service
 * endpoint to end with an error and communicate it to its clients.
 */
class EndpointLog extends DefaultErrorMemoryLog {
	public function throwIfErrors() {
		if ($this->hasErrors()) throw new EndpointException($this);
	}
	
	public function errorAndThrow($path, $message, $code = null, array $details = null) {
		$this->error($path, $message, $code, $details);
		throw new EndpointException($this);
	}
}