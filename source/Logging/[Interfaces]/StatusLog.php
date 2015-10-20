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
interface StatusLog extends Log {
	const ERROR_LABEL = 'error';
	const WARNING_LABEL = 'warning';
	const INFO_LABEL = 'info';
	const SUCCESS_LABEL = 'success';
	
	const ERROR_FLAG = 1;
	const WARNING_FLAG = 2;
	const INFO_FLAG = 4;
	const SUCCESS_FLAG = 8;
	
	/**
	 * Returns the bit flag mask for the event types the log will record. See the *_FLAG constants.
	 * 
	 * Reading the value from this method and implementing it by not passing events of the masked out types should be
	 * considered an optional optimization. Even if masked out types are added to the log, they'll be silently ignored.
	 * 
	 * The recommended default mask level for logs is 15 (i.e. 0b1111, all four major types), but implementations may
	 * choose a different default.
	 * 
	 * @return int
	 */
	function getMask();
	
	/**
	 * Represents a negative event that altered the control flow of an operation, i.e. it failed to complete properly.
	 * 
	 * @param array $path
	 * Default null. A path can be specified as a list of strings, to demonstrate the location of origin for an event.
	 * 
	 * @param string $message
	 * Default null. A human readable message describing the event.
	 * 
	 * @param string $code
	 * Default null. Machine readable event code. If you set the message to null, you must pass a non-null code.
	 * 
	 * @param array $details
	 * Default null. A dictionary with machine readable event context (arbitrary variables describing the event).
	 * 
	 * @throws LogException
	 */
	function error($path = null, $message = null, $code = null, array $details = null);	
	
	/**
	 * Represents a non-critical negative event, foreshadowing a possible problem.
	 * 
	 * @param array $path
	 * Default null. A path can be specified as a list of strings, to demonstrate the location of origin for an event.
	 * 
	 * @param string $message
	 * Default null. A human readable message describing the event.
	 * 
	 * @param string $code
	 * Default null. Machine readable event code. If you set the message to null, you must pass a non-null code.
	 * 
	 * @param array $details
	 * Default null. A dictionary with machine readable event context (arbitrary variables describing the event).
	 * 
	 * @throws LogException
	 */
	function warning($path = null, $message = null, $code = null, array $details = null);
	
	/**
	 * Represents a neutral (neither positive, nor negative) information message.
	 * 
	 * @param array $path
	 * Default null. A path can be specified as a list of strings, to demonstrate the location of origin for an event.
	 * 
	 * @param string $message
	 * Default null. A human readable message describing the event.
	 * 
	 * @param string $code
	 * Default null. Machine readable event code. If you set the message to null, you must pass a non-null code.
	 * 
	 * @param array $details
	 * Default null. A dictionary with machine readable event context (arbitrary variables describing the event).
	 * 
	 * @throws LogException
	 */
	function info($path = null, $message = null, $code = null, array $details = null);
	
	/**
	 * Represents a positive event, usually signifying the successful completion of an operation in progress.
	 * 
	 * @param array $path
	 * Default null. A path can be specified as a list of strings, to demonstrate the location of origin for an event.
	 * 
	 * @param string $message
	 * Default null. A human readable message describing the event.
	 * 
	 * @param string $code
	 * Default null. Machine readable event code. If you set the message to null, you must pass a non-null code.
	 * 
	 * @param array $details
	 * Default null. A dictionary with machine readable event context (arbitrary variables describing the event).
	 * 
	 * @throws LogException
	 */
	function success($path = null, $message = null, $code = null, array $details = null);
}