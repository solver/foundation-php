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
namespace Solver\Lcg;

/**
 * IMPORTANT: This class is a byproduct of developing & testing the LCG. It might go away in the future (it won't be
 * necessary as ConfigBuilder will ensure a correct mathematical proof of a full period generator).
 * 
 * This class verifies empirically (by trying every value of the generator) that a generator config is full-period.
 * 
 * The math in ConfigBuilder proves the same much faster, but until we're sure everything is stable, this class allows
 * for some peace of mind when configuring mission-critical generators that should be right from day one.
 * 
 * Note that this class tests a generator by running it full period forward and backwards. It's slow for large periods,
 * so make sure to disable time limits and run it in CLI, and not in a web thread.
 */
class ConfigEmpiricalVerifier {
	/**
	 * Verifies a generator config results in a full-period & properly reversible LCG, or throws.
	 * 
	 * @param mixed $config
	 * As provided by ConfigBuilder::build().
	 * 
	 * @param int|string $value
	 * Optional start value (shouldn't matter, default 0).
	 * 
	 * @param int|string $value
	 * Optional start value (shouldn't matter, default 0).
	 * 
	 * @param \Closure $didUpdateProgress
	 * (\GMP $processedCount, \GMP $totalPeriod) => void
	 *  
	 * An optional handler that will be invoked roughly every 10 seconds with progress of the test. Useful when you want
	 * to display progress when running as a CLI process.
	 *  
	 * @return bool
	 * Always returns true (if it fails, it throws).
	 * 
	 * @throws \Exception
	 * If any of the verified conditions aren't true.
	 */
	public static function verify($config, $value = 0, \Closure $didUpdateProgress = null) {
		$gen = new Generator($config);
		
		$value = (string) $value;
		$v = $value;
		$period = gmp_init($config[0]);
		
		$c = 0;
		$lastUpdateTime = microtime(1);
		for ($i = gmp_init(0); $i < $period; $i++) {
			$c++;
			if ($c === 1000) {
				$c = 0;
				
				if ($didUpdateProgress !== null) {
						
					$newUpdateTime = microtime(1);
					
					if ($newUpdateTime - $lastUpdateTime >= 10) {
						$lastUpdateTime = $newUpdateTime;
						$didUpdateProgress($i, $period);
					}					
				}
			}
			
			$vPrev = $v;
			$v = $gen->advance($v);
			$vInv = $gen->reverse($v);
			
			if ($vInv !== (string) $vPrev) {
				throw new \Exception('Generated value ' . $v . ' was inverted to ' . $vInv . ' instead of ' . $vPrev . '.');
			}
		
			if ($v == $value && ($i + 1) != $period) {
				throw new \Exception('The initial value ' . $value . ' was encountered before the period was complete. This is not a full-period generator configuration.');
			}
		}
		
		if ((string) $value !== (string) $v) {
			throw new \Exception('Final value does not match initial value: ' . $v . ' instead of ' . $value . '.');
		}
		
		return true;
	}
}