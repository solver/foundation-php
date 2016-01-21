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

use Solver\Lab\MonkeyPatch;
class LogUtils {
	/**
	 * Imports all events from a MemoryLog into another Log with an optional path (re)map.
	 * 
	 * @param MemoryLog $destinationLog
	 * Put events in here...
	 * 
	 * @param MemoryLog $sourceLog
	 * Get events out of here...
	 * 
	 * @param array $map
	 * See self::applyPathMap().
	 * 
	 * @deprecated
	 * Use pipe() and applyPathMap().
	 */
	public static function import(Log $destinationLog, MemoryLog $sourceLog, $map = null) {	
		// FIXME: This method is obsolete. Remove.
		if ($map) {
			$destinationLog->log(...self::applyPathMapFilter($sourceLog->getEvents(), $map));
		} else {
			$destinationLog->log(...$sourceLog->getEvents());
		}
	}
	
	/**
	 * Copies all events from a MemoryLog instance to a Log instance with an optional transform/filtering step.
	 * 
	 * @param MemoryLog $fromLog
	 * Log to get all events from.
	 * 
	 * @param Log $toLog
	 * Log to append all events to.
	 * 
	 * @param array|\Closure $mapOrFilter
	 * #Filter: (list<event: dict>) => list<event: dict>; A closure that will receive one or more events from the 
	 * source log and can return the events to be used instead (you can return an empty array to filter out all events).
	 */
	public static function pipe(MemoryLog $fromLog, Log $toLog, \Closure $filter = null) {
		$events = $fromLog->getEvents();
		if (!$events) return;
		
		if ($filter) {
			$toLog->log(...$filter($events));
		} else {
			$toLog->log(...$events);
		}
	}
	
	/**
	 * Returns a copy of the given events with paths processed according to the given map.
	 * 
	 * @param array $events
	 * list<event: dict>;
	 * 
	 * @param array $map
	 * dict; A dict of "old base" => "new base" rules, which will be used to alter the paths of the imported events.
	 * Dot is used as a delimiter in order to express multiple segments.
	 * 
	 * Be aware that for every event path, ONLY ONE rule will be matched and applied, and this is the LAST RULE that
	 * matches the path base in your event (rules are processed in reverse). The reverse processing is designed, so you
	 * can lay out your map naturally from least to most specific rules (the most specific rule will apply to your 
	 * path).
	 * 
	 * Note that events with path set to null or empty array (both semantically identical, meaning "no path") can have
	 * a path assigned to them by passing ['' => 'foo.bar']. The result for path [] will be ['foo', 'bar'], and these
	 * segments will be prepended to all other paths as well.
	 * 
	 * TODO: This certainly needs to be explained better.
	 * 
	 * @return array
	 * list<event: dict>;
	 */
	public static function applyPathMapFilter(array $events, array $map) {
		if (!$events) return [];
		if (!$map) return $events;
		
		// FIXME: We're just adapting here old "string path" code, but we don't account fully for dots in segment names
		// (unlikely) and code can probably be optimized, so rewrite to arrays when possible.
		
		// Build a regex with the map keys reverse (later entries take priority, but regex returns first match).	
		$mapKeys = \array_reverse(\array_keys($map));
		
		foreach ($mapKeys as & $val) $val = \preg_quote($val, '%');
		
		$regex = '%(' . \implode('|', \array_reverse(\array_keys($map))) . ')(?|$()()|(\.?)(.*))%AD';
			
		foreach ($events as & $event) {				
			$path = isset($event['path']) ? \implode('.', $event['path']) : '';
				
			if (\preg_match($regex, $path, $matches)) {
				$replacement = $map[$matches[1]];
				if ($replacement === '') $path = $matches[3];
				else $path = $replacement . '.' . $matches[2]  . $matches[3];
			}

			if ($path === '') {
				if (isset($event['path'])) unset($event['path']);
			} else {
				$event['path'] = explode('.', trim($path, '.'));
			}
		}
		
		return $events;
	}
	
	
	public static function getPathMapFilter(array $map) {
		// TODO: Move the logic here from applyPathMap so we can compile the map once, then reuse it.
		return function ($events) use ($map) {
			return self::applyPathMapFilter($events, $map);
		};
	}
	
	/**
	 * Provides a summary of the last N errors in the log (starting from the last error), if the log is readable.
	 * 
	 * If it isn't, it returns a default string message.
	 * 
	 * IMPORTANT: The format of the returned string is intended for human consumption and not for machine parsing. The
	 * exact format of the string may change as this library gets updated.
	 * 
	 * @param Log $log
	 * Log to read errors from.
	 * 
	 * @param int $maxErrorCount
	 * Optional (default = 16). Maximum number of errors to include in the summary.
	 * 
	 * @return string
	 * Error summary for the log.
	 */
	public static function getErrorSummary(Log $log, $maxErrorCount = 16) {
		// TODO: Dig into DelegatingLog fetch parent chain if readable interface is not found. 
		if ($log instanceof StatusMemoryLog) {
			$errors = $log->getErrors();
		} elseif ($log instanceof MemoryLog) {
			$errors = [];
			$events = $log->getEvents();
			foreach ($events as $event) {
				if (isset($event['type']) && $event['type'] === 'error') {
					$errors[] = $event;
				}
			}
		} elseif ($log instanceof DelegatingStatusLog) {
			// We'll dig into this log's delegate to get to readable events. Typically this would be a bad idea, but for
			// the mere purpose of human-assisted debugging, it's ok to get to something readable, rather than not.
			do $log = MonkeyPatch::get(DelegatingStatusLog::class, $log, 'log');
			while ($log instanceof DelegatingStatusLog);
			
			return self::getErrorSummary($log, $maxErrorCount);
		} else {
			return 'The log does not provide a supported read interface.';
		}
		
		if (!$errors) return 'The log does not contain errors.';
		
		$errorCount = count($errors);
		
		// Grab last few & reverse, so last error is first.
		$errors = array_reverse(array_slice($errors, -$maxErrorCount, $maxErrorCount));
		
		$messages = [];
		
		foreach ($errors as $error) {
			$message = '';
			
			if (isset($error['path']) && $error['path']) {
				$message = '(@' . implode('.', $error['path']) . ') ';
			}
			
			if (isset($error['message'])) {
				$message .= $error['message'];
			} elseif (isset($error['code'])) {
				$message .= 'Code: ' + $error['code'];
			} else {
				$message .= 'The event provides no message or code.';
			}
			
			$messages[] = $message;
		}
		
		if ($errorCount > $maxErrorCount) {
			$messages[] = '' + ($errorCount - $maxErrorCount) + ' more errors...';
		}

		return implode("\n", $messages);
	}
}