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
	 * A dict of "old base" => "new base" rules, which will be used to alter the paths of the imported events.
	 * 
	 * Be aware that for every event path, ONLY ONE rule will be matched and applied, and this is the LAST RULE that
	 * matches the path base in your event (rules are processed in reverse). The reverse processing is designed, so you
	 * can lay out your map naturally from least to most specific rules (the most specific rule will apply to your 
	 * path).
	 * 
	 * Note that events with path set to null or empty string (both semantically identical, meaning "no path") can have
	 * a path assigned to them by passing [null => 'foo.bar']. The result for path '' will be 'foo.bar', non-empty paths
	 * won't be affected.
	 * 
	 * TODO: This certainly needs to be explained better.
	 * TODO: Add closure ($error => $error|null) as an option for $map, so mapping can be more flexible.
	 */
	public static function import(Log $destinationLog, MemoryLog $sourceLog, $map = null) {	
		if ($map) {
			// Build a regex with the map keys reverse (later entries take priority, but regex returns first match).	
			$mapKeys = \array_reverse(\array_keys($map));
			
			foreach ($mapKeys as & $val) $val = \preg_quote($val, '%'); 
			
			$regex = '%(' . \implode('|', \array_reverse(\array_keys($map))) . ')(?|$()()|(\.?)(.*))%AD';
				
			foreach ($sourceLog->getEvents() as $event) {				
				$path = $event['path'];
				
				// Null and empty string are semantically equivalent for a path (null is the canonical value for it).
				if ($path === null) $path = '';
					
				if (\preg_match($regex, $path, $matches)) {
					$replacement = $map[$matches[1]];
					if ($replacement === '') $path = $matches[3];
					else $path = $replacement . $matches[2]  . $matches[3];
				}

				if ($path === '') $path = null;
				
				$event['path'] = $path;
				
				$destinationLog->log($event);
			}
		} else {
			foreach ($sourceLog->getEvents() as $event) {
				$destinationLog->log($event);
			}
		}
	}
}