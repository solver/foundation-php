<?php
namespace Solver\Lab;

/**
 * TODO: PHPDoc.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class ListFormat extends AbstractFormat implements Format {
	protected $rules = [];
	protected $childrenFormat = null;
	
	
	public function extract($value, ErrorLog $log, $path = null) {
		if (!\is_array($value)) {
			$log->addError($path, 'Please provide a list.');
			return null;
		}
		
		$errorCount = $log->getErrorCount();
		$childrenFormat = $this->childrenFormat;
		$filtered = [];
				
		// Extract list (we require a sequential, zero based list, anything else isn't a list, the order of the actual
		// keys in the input, however is not important /the result will be sorted/).
		for ($i = 0;; $i++) {
			if (\key_exists($i, $value)) {
				$filtered[$i] = $childrenFormat 
					? $childrenFormat->extract($value[$i], $log, $path === null ? $i : $path . '.' . $i) 
					: $value[$i];
			} else {
				break;
			}
		}
		
		$value = $filtered;
		
		if ($log->getErrorCount() > $errorCount) {
			return null;
		} else {
			return parent::extract($value, $log, $path);
		}
	}
	
	/**
	 * @param \Solver\Lab\Format $format
	 * 
	 * @return self
	 */
	public function children(Format $format) {
		if ($this->rules) throw new \Exception('You should call method children() before any test*() or filter*() calls.');
		if ($this->childrenFormat !== null) throw new \Exception('You should call method children() only once.');
		
		$this->childrenFormat = $format;
		
		return $this;
	}
	
	/**
	 * @param int $min
	 * 
	 * @return self
	 */
	public function testLengthMin($min) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($min) {
			$count = \count($value);
			
			if ($count < $min) {
				$log->addError($path, "Please provide a list with at least $min items.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * @param int $max
	 * 
	 * @return self
	 */
	public function testLengthMax($max) {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($max) {
			$count = \count($value);
			
			if ($count > $max) {
				$log->addError($path, "Please provide a list with at most $count items.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
	
	/**
	 * @return self
	 */
	public function testNotEmpty() {
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($max) {
			$count = \count($value);
			
			if ($count == 0) {
				$log->addError($path, "Please provide a list with one or more items.");
				return false;
			} else {
				return true;
			}
		}];
		
		return $this;
	}
}