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
namespace Solver\Report;

// TODO: Document.
interface ErrorLog extends Log {
	const TYPE_ERROR = 'error';
	
	/**
	 * Represents a negative event that altered the control flow of an operation, i.e. it failed to complete properly.
	 * 
	 * @param string $path
	 * A path can be specified to demonstrate the location of origin for an event. Set to null if not applicable.
	 * 
	 * @param string $message
	 * A human readable message describing the event. If you pass code (next argument), you can set the message to null.
	 * 
	 * @param string $code
	 * Default null. Machine readable event code. If you set the message to null, you must pass a non-null code.
	 * 
	 * @param array $details
	 * Default null. A dictionary with machine readable event context (arbitrary variables describing the event).
	 * 
	 * @throws LogException
	 */
	function error($path, $message, $code = null, array $details = null);
}