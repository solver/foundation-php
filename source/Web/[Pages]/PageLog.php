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
namespace Solver\Web;

use Solver\AccordX\ExpressLog;

/** 
 * This log delegates to the caller-provided log. Adding errors here can is used to communicate failure by the page
 * to produce a response (not found, forbidden, etc.) which result in a generic error page rendered by the caller.
 * 
 * This class contains convenience methods for the common error types. The caller may support more codes, which you can
 * add and throw via a custom error event (simply set the HTTP status code as the event code).
 * 
 * See also Page::stop*() methods for non-failure ways to abort processing a controller while returning valid responses,
 * such as redirects or custom error pages.
 */
class PageLog extends ExpressLog {	
	/**
	 * Logs a 401 Unauthorized error and throws ActionException.
	 * 
	 * @throws \Solver\Accord\Exception
	 */
	public function throwUnauthorized() {
		$this->log(['type' => 'error', 'code' => 401]);
		$this->throwIfHasErrors();
	}
	
	/**
	 * Logs a 403 Forbidden error and throws ActionException.
	 * 
	 * @throws \Solver\Accord\Exception
	 */
	public function throwForbidden() {
		$this->log(['type' => 'error', 'code' => 403]);
		$this->throwIfHasErrors();
	}
	
	/**
	 * Logs a 404 Not Found error and throws ActionException.
	 * 
	 * @throws \Solver\Accord\Exception
	 */
	public function throwNotFound() {
		$this->log(['type' => 'error', 'code' => 404]);
		$this->throwIfHasErrors();
	}
	
	/**
	 * Logs a 500 Internal Server Error and throws ActionException.
	 * 
	 * @throws \Solver\Accord\Exception
	 */
	public function throwInternal() {
		$this->log(['type' => 'error', 'code' => 500]);
		$this->throwIfHasErrors();
	}
}