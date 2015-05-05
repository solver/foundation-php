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
namespace Solver\Lab;

/**
 * A primitive test runner (to be enhanced).
 *
 * Usage:
 *
 * <code>
 * $ts = new TestSuite();
 * $ts->add('2 + 2 must be 4', function () {
 * return 2 + 2 == 4;
 * });
 * $ts->run();
 * </code>
 */
class TestSuite {
	protected $before, $after, $tests = [];

	function add($description, \Closure $test) {
		$this->tests[] = [$description, $test];
	}

	function before(\Closure $before) {
		$this->before = $before;
	}

	function after(\Closure $after) {
		$this->after = $after;
	}

	function run() {
		$hasErrors = false;
		
		foreach ($this->tests as $test) {
			if ($this->before) $this->before->__invoke();
			
			try {
				if (!$test[1]()) throw new \Exception('Assertion failed.');
			} catch (\Exception $e) {
				echo "$test[0] failed with message: {$e->getMessage()} <br>";
				$hasErrors = true;
			}
			
			if ($this->after) $this->after->__invoke();
		}
		
		echo $hasErrors ? 'Test execution completed with some errors.<br>' : 'Test execution completed without errors.<br>';
	}
}