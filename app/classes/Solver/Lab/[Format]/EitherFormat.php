<?php
namespace Solver\Lab;

/**
 * Tests the given formats in sequence, until one of them successfully validates the value. Each format will get the 
 * value infiltered, and the errors of each format will be erased, if there's a next one to attempt.
 * 
 * TODO: PHPDoc.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class EitherFormat extends AbstractFormat implements Format {	
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
	
	/**
	 * TODO: Old name for add(). Remove when no longer used.
	 *
	 * @param \Solver\Lab\Format $format
	 * 
	 * @return self
	 */
	public function attempt(Format $format) {
		return $this->add($format);
	}
	
	public function extract($value, ErrorLog $log, $path = null) {
		$formatMaxIndex = \count($this->formats) - 1;
		
		foreach ($this->formats as $i => $format) {
			// TODO: We shouldn't use a ServiceLog, it's only for services, but for now it'll do until we have a generic
			// log class (or specialized for Format classes).
			$tempLog = new ServiceLog();
			$tempValue = $format->extract($value, $tempLog, $path);
			
			if ($tempLog->hasErrors()) {
				if ($i == $formatMaxIndex) {
					// We can't rely on import() as it's not on the ErrorLog interface (refactoring may fix that).
					foreach ($tempLog->getAllEvents() as $event) {
						$log->addError($event['path'], $event['message'], $event['code'], $event['details']);
					}
					return $tempValue;
				}
			} else {
				$value = $tempValue;
				break;
			}
		}
		
		return parent::extract($value, $log, $path);
	}
}