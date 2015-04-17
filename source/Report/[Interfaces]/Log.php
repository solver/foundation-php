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
interface Log {
	/**
	 * Writes an event to the log.
	 * 
	 * @param array $event
	 * dict...
	 * - type: string; Depending on the log, only certain event types may be permitted in the log.
	 * - path: null|string; A path can be specified to demonstrate the location of origin for an event. Set to null if
	 * not applicable.
	 * - message: null|string; A human readable message describing the event. If you pass code (next field), you can
	 * set the message to null.
	 * - code: null|string; Machine readable event code. If you set the message to null, you must pass a non-null code.
	 * - details: null|dict; A dictionary with machine readable event context (arbitrary variables describing the
	 * event).
	 * 
	 * @throws LogException
	 */
	function log(array $event);
}