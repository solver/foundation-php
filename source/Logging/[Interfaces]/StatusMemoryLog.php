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
namespace Solver\Logging;

// TODO: Document.
interface StatusMemoryLog extends MemoryLog, StatusLog {
	/**
	 * @return bool
	 * True if the log contains errors, false if it doesn't.
	 */
	function hasErrors();
	
	/**
	 * Returns all errors in the log.
	 * 
	 * @return array
	 * Returns a list of errors (empty list if the log has no errors).
	 */
	function getErrors();
	
	/**
	 * Returns the last error.
	 * 
	 * @return array|null
	 * Returns the last error or null if the log has no errors.
	 */
	function getLastError();
}