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
 * A log capable of holding error events.
 */
interface ErrorLog {
	/**
	 * @param string $path
	 * Error path. The recommended practice is to use "input.{fieldname}" for validation, and dots for deep input paths,
	 * for example: "input.user.name". Path semantics for other error types is undefined and can be chosen depending on
	 * your needs. 
	 * 
	 * If you need no path in your error, set it to null or empty string (null is canonical, but both mean "no path").
	 * 
	 * @param string $message
	 * @param string $code
	 * @param string $details
	 */
	public function addError($path = null, $message = null, $code = null, $details = null);
	
	// TODO: Maybe this shouldn't be here.
	public function hasErrors();
	
	// TODO: Maybe this shouldn't be anywhere.
	public function getErrorCount();
}