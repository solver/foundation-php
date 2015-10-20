<?php
namespace Solver\Accord;

use Solver\Logging\Log;

/**
 * DO NOT USE: This class is an internal implementation detail and may change or go away without warning.
 * 
 * TODO: Refactor this log away once we have an anon log class, or at least move it to its own file. We can also
 * make it explicitly a StatusLog so we don't have to wrap this in DelegatingStatusLog.
 */
class InternalTempLog implements Log {
	protected $events;
	protected $path;
	
	public function __construct(& $events, $path) {
		$this->events = & $events;
		$this->path = $path;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Logging\Log::log()
	 */
	public function log(array $event, ...$events) {
		if ($this->path) {
			if (isset($event['path'])) {
				$event['path'] = array_merge($this->path, $event['path']);
			} else {
				$event['path'] = $this->path;
			}
		}
		
		$this->events[] = $event;
		
		// TODO: Optimize, don't call self?
		if ($events) foreach ($events as $event) $this->log($event);
	}
}