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
namespace Solver\Accord;

/**
 * DO NOT USE. This is an internal implementation detail of Solver\Accord's formats & transforms, and may change or go
 * away without warning.
 */
class InternalTransformUtils {
	public static function addErrorTo(& $events, $path, $message, $code = null, $details = null) {
		if ($path) {
			$event = ['type' => 'error', 'path' => $path];
		} else {
			$event = ['type' => 'error'];
		}
		
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$events[] = $event;
	}
	
	public static function addWarningTo(& $events, $path, $message, $code = null, $details = null) {
		if ($path) {
			$event = ['type' => 'warning', 'path' => $path];
		} else {
			$event = ['type' => 'warning'];
		}
		
		if (isset($message)) $event['message'] = $message;
		if (isset($code)) $event['code'] = $code;
		if (isset($details)) $event['details'] = $details;
		$events[] = $event;
	}
	
	public static function mergeTo(& $events, $eventsToMerge) {
		if ($eventsToMerge) {
			if ($events) {
				array_push($events, ...$eventsToMerge);
			} else {
				$events = $eventsToMerge;
			}
		}
	}
	
	/**
	 * Pipelines the given closures, each function using a format & semantics identical to FastActon::fastApply().
	 * 
	 * @param array $functions
	 * list<#Function>;
	 * 
	 * #Function: ($input: any, & $output: any, $mask, & $events: list<dict>, $path) => bool; Matches the format and
	 * semantics of FastAction::fastApply().
	 * 
	 * @param mixed $input
	 * Same as FastAction::fastApply().
	 * 
	 * @param mixed & $output
	 * Same as FastAction::fastApply().
	 * 
	 * @param int $mask
	 * Same as FastAction::fastApply().
	 * 
	 * @param mixed & $events
	 * Same as FastAction::fastApply().
	 * 
	 * @param null|array $path
	 * Same as FastAction::fastApply().
	 * 
	 * @return null|mixed
	 * Transformed value (or null).
	 */
	public static function fastApplyFunctions($functions, $input, & $output, $mask, & $events, $path) {
		if ($functions) {
			foreach ($functions as $function) {
				if (!$function($input, $output, $mask, $events, $path)) return false;
				$input = $output;
			}
		} else {
			$output = $input;
		}
		return true;
	}
}