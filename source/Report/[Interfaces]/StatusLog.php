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
interface StatusLog extends ErrorLog {
	const TYPE_INFO = 'info';
	const TYPE_SUCCESS = 'success';
	const TYPE_WARNING = 'warning';	
	
	
	/**
	 * Represents a neutral (neither positive, nor negative) information message.
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
	function info($path, $message, $code = null, array $details = null);
	
	/**
	 * Represents a positive event, usually signifying the successful completion of an operation in progress.
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
	function success($path, $message, $code = null, array $details = null);
	
	/**
	 * Represents a non-critical negative event, foreshadowing a possible problem.
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
	function warning($path, $message, $code = null, array $details = null);
}