<?php
namespace Solver\Shake;

/**
 * Used by ControllerLog and ServiceLog.
 * 
 * This trait will probably be refactoring as a part of a base Log class, so don't grow "attached" to it.
 * 
 * @author Stan Vass
 * @copyright © 2013-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
trait ImportEventsTrait {
	/**
	 * Imports a all events from an ErrorProvider into the log with an optional path (re)map.
	 *  
	 * Examples of EventProvider are this ControllerLog, Processor and ModelException.
	 * 
	 * @param \Solver\Sloppy\EventProvider $eventProvider
	 * An event provider.
	 * 
	 * @param array $map
	 * A dict of "old base" => "new base" rules, which will be used to alter the paths of the imported events.
	 * 
	 * Set the map from less to more specific mappings (it's processed in reverse). The base of the event paths is 
	 * replaced. Events with path set to null (default) are not affected, as null is treated as "no path".
	 * 
	 * TODO: This certainly needs to be explained better.
	 */
	public function import(EventProvider $eventProvider, array $map = null) {		
		if ($map) {
			// Build a regex with the map keys reverse (later entries take priority, but regex returns first match).	
			$mapKeys = \array_reverse(\array_keys($map));
			
			foreach ($mapKeys as & $val) $val = \preg_quote($val, '%'); 
			
			$regex = '%(' . \implode('|', \array_reverse(\array_keys($map))) . ')(?|$()()|(\.?)(.*))%AD';
							
			foreach ($eventProvider->getAllEvents() as $event) {				
				$path = $event['path'];
				
				if ($path !== null && \preg_match($regex, $path, $matches)) {
					$replacement = $map[$matches[1]];
					if ($replacement === '') $path = $matches[3];
					else $path = $replacement . ($matches[3] === '' ? '' : '.') . $matches[3];
				}

				$this->addError($path, $event['message'], $event['code'], $event['details']);
			}
		} else {
			foreach ($eventProvider->getAllEvents() as $event) {
				$this->addError($event['path'], $event['message'], $event['code'], $event['details']);
			}
		}
	}
}