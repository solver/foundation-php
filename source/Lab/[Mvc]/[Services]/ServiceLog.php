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

use Solver\Report\DefaultTransientErrorLog;

/**
 * A log that can contain error events, with an ability to throw a ServiceException (containing the log), when there
 * are errors in the log.
 * 
 * Throwing a ServiceException with one or more errors by manipulating a ServiceLog is the standard way for a service
 * endpoint to end with an error and communicate it to its clients.
 */
class ServiceLog extends DefaultTransientErrorLog {
	public function throwIfErrors() {
		if ($this->hasErrors()) throw new ServiceException($this);
	}
	
	public function errorAndThrow($path, $message, $code = null, array $details = null) {
		$this->error($path, $message, $code, $details);
		throw new ServiceException($this);
	}
}