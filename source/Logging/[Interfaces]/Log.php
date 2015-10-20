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

// TODO: Document. Document semantics that this interface allows any event type, but sub-interface may restrict to
// specific types (status only allows its standard types none other).
interface Log {
	/**
	 * Writes one or more events to the log. 
	 * 
	 * IMPORTANT: This is an atomic operation. In case of failure, log implements MUST NOT commit partial results to the 
	 * log.
	 * 
	 * Specifics about the event format:
	 * 
	 * - Please note that while "message" and "code" are both optional fields, it's preferred to provide at least one
	 * of both, so callers can make sense of the error context. This is not mandated, but highly recommended.
	 * 
	 * - Fields "path", "message", "code", "details" can be set to null, but this is semantically equivalent to not 
	 * setting them at all. The prefered choice for PHP arrays is not to set the keys at all, rather than use null.
	 * For events materialized as an object, the unused properties can be null.
	 * 
	 * @param array $event
	 * dict...
	 * - type: string; Depending on the log, only certain event types may be permitted in the log.
	 * - path?: null|list<string>; A path can be specified to demonstrate the input location of origin for an event.
	 * - message?: null|string; A human readable message describing the event.
	 * - code?: null|string; Machine readable event code.
	 * - details?: null|dict; An arbitrary dictionary with machine readable event context.
	 * 
	 * @param array ...$events
	 * Zero or more additional events to push onto the log.
	 * 
	 * @throws LogException
	 */
	function log(array $event, array ...$events);
}