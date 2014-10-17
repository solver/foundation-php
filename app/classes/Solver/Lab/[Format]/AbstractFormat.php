<?php
namespace Solver\Lab;

/**
 * TODO: PHPDoc.
 * 
 * @author Stan Vass
 * @copyright © 2011-2014 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */
class AbstractFormat implements Format {
	protected $rules = [];
	
	public function extract($value, ErrorLog $log, $path = null) {
		
		foreach ($this->rules as $rule) {
			list($type, $closure) = $rule;
			
			switch ($type) {
				case 'test':
					if (!$closure($value, $log, $path)) return null;
					break;
				
				case 'filter':
					$value = $closure($value, $log, $path);
					break;
					
				default:
					// Catch developer mistakes when adding a rule.
					throw new \Exception('Bad type.'); 
			}
		}
		
		return $value;
	}
		
	/**
	 * The closure must accept arguments ($value, ErrorLog $log, $path) and do one of these:
	 * 
	 * - If the value passes the test, do not log any errors.
	 * - If the value doesn't pass the test, log one or more errors (mind the $path given while logging).
	 * 
	 * @return self
	 */
	public function testCustom(\Closure $test) {
		// Internally tests must return true/false, hence this wrapper to emulate that. Eventually all test 
		// functions, including the internal ones, will work only with the log without having to any return result.
		$this->rules[] = ['test', function ($value, ErrorLog $log, $path) use ($test) {
			$errorCount = $log->getErrorCount();
			$test($value, $log, $path);
			return $log->getErrorCount() == $errorCount;
		}];
		
		return $this;
	}
	
	/**
	 * The closure must accept argument ($value) and return the modified $value.
	 * 
	 * @return self
	 */
	public function filterCustom(\Closure $filter) {
		$this->rules[] = ['filter', $filter];
		
		return $this;
	}
}