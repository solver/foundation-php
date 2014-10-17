<?php
namespace Solver\Lab;

/**
 * Takes multiple formats and runs the value through each of them in order, feeding the output of one as the input of
 * the next one.
 * 
 * The processing chain is broken if the value is tested invalid by any of the formats.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class PipedFormat extends AbstractFormat implements Format {
	protected $formats;
			
	/**
	 * @param \Solver\Lab\Format $format
	 * 
	 * @return self
	 */
	public function add(Format $format) {
		if ($this->rules) throw new \Exception('You should call method add() before any test*() or filter*() calls.');
		
		$this->formats[] = $format;
		
		return $this;
	}
	
	public function extract($value, ErrorLog $log, $path = null) {
		// TODO: We shouldn't use a ServiceLog, it's only for services, but for now it'll do until we have a generic
		// log class (or specialized for Format classes).
		$tempLog = new ServiceLog();
				
		foreach ($this->formats as $i => $format) {
			$value = $format->extract($value, $tempLog, $path);
			if ($tempLog->hasErrors()) break;
		}
		
		if ($tempLog->hasErrors()) {
			// We can't rely on import() as it's not on the ErrorLog interface (refactoring may fix that).
			foreach ($tempLog->getAllEvents() as $event) {
				$log->addError($event['path'], $event['message'], $event['code'], $event['details']);
			}
		}
		
		return parent::extract($value, $log, $path);
	}
}