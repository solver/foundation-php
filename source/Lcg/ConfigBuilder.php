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
 * Use this class to build a valid Generator configuration.
 * 
 * IMPORTANT: 
 * Validating the configuration requires doing some math. Because PHP initializes its object graph on every request,
 * it's recommended to var_export() the output of build() once you settle on a suitable configuration and reuse it in
 * your code to create Generator instances in every request.
 * 
 * If you run your PHP app in a persistent process, this optimization step is not as necessary.
 */
class ConfigBuilder {	
	/** 
	 * Builds and returns a configuration you can pass to a Generator during construction.
	 * 
	 * Strings representing numbers are supported. Large integer are supported (not limited by machine int size).
	 * 
	 * This builder ensures the configuration results in generators with a full period, which also means there is 1:1 
	 * mapping between input and output values within the period (all values are used; no collisions).
	 * 
	 * @param int|string $period
	 * The input & output of the generator can vary between 0 and ($period - 1) (a.k.a. the LCG "modulo" parameter).
	 * 
	 * Constraints: 
	 * - 0 < "period" (the period is typically, but not necessarily a power of 2 or 10 for practical reasons: using an
	 * exact number of bits or digits to fit a given binary or decimal integer size).
	 * 
	 * @param int|string $multiplier
	 * LCG multiplier.
	 * 
	 * Constraints:
	 * - 0 < "multiplier" < "period"
	 * - ("multiplier" - 1) is divisible by all prime factors of the "period"
	 * - ("multiplier" - 1) is divisible by 4 if the "period" is divisible by 4
	 * - "multiplier" and "period" must be co-prime.
	 * 
	 * @param int|string $offset
	 * LCG offset (increment).
	 * 
	 * Constraints: 
	 * - 0 <= "offset" < "period"
	 * - "offset" and "period" must be co-prime.
	 * 
	 * @throws \Exception
	 * If any of the parameters are invalid.
	 * 
	 * @return mixed
	 * Configuration options for constructing a Generator instance.
	 */
	public static function build($period, $multiplier, $offset) {
		$period = gmp_init($period);
		$multiplier = gmp_init($multiplier);
		$offset = gmp_init($offset);
	
		if ($period <= 0) {
			throw new \Exception('Parameter "period" doesn\'t satisfy constraint: 0 < "period".');
		}
		
		if ($multiplier <= 0 || $multiplier > $period) {
			throw new \Exception('Parameter "multiplier" doesn\'t satisfy constraint:  0 < "multiplier" < "period".');
		}
		
		if ($offset < 0 || $offset > $period) {
			throw new \Exception('Parameter "offset" doesn\'t satisfy constraint:  0 <= "offset" < "period".');
		}
		
		if (gmp_gcd($period, $offset) != 1) {
			throw new \Exception('Parameters "period" and "offset" don\'t satisfy constraint: "period" and "offset" should be co-prime.');
		}
		
		$multiplierMinusOne = $multiplier - 1;
				
		foreach (array_unique(self::getPrimeFactors($period)) as $factor) {
			if ($multiplierMinusOne % $factor != 0) {
				throw new \Exception('Parameters "period" and "multiplier" don\'t satisfy constraint: ("multiplier" - 1) should divide exactly by all the prime factors of the "period".');
			}
		}		
	
		if ($period % 4 == 0 && $multiplierMinusOne % 4 != 0) {
			throw new \Exception('Parameters "period" and "multiplier" don\'t satisfy constraint: if "period" divides exactly by 4, ("multiplier" - 1) should divide exactly by 4.');
		}
	
		// TODO: Read about Hull-Dobell's theorem.
		// We don't have to check this one explicitly according to Hull-Dobell's theorem, but it's an expectation of
		// well-selected parameters. So we're leaving it in, while we know for sure it's not needed.
		// Alternatively, this is not a requirement for a full period, but requirement for computing the inverse, so it
		// may be a flag in this method where users specify whether they want it reversible. If yes, we check this here
		// and wr produce inverse, else we don't, and the generator throws if people call reverse().
		if (gmp_gcd($period, $multiplier) != 1) {
			throw new \Exception('Parameters "period" and "multiplier" don\'t satisfy constraint: "period" and "multiplier" should be co-prime.');
		}
		
		// We export the numbers as strings, so people can easily var_export() a compact config from ConfigBuilder and
		// paste it in their code, instead of running ConfigBuilder every time.
		return [
			gmp_strval($period), 
			gmp_strval($multiplier), 
			gmp_strval($offset), 
			gmp_strval(gmp_invert($multiplier, $period)),
		];
	}
	
	public static function getPrimeFactors(\GMP $num) {
		// TODO: Seek to optimize this method, or cap it to a size where it won't take too long without explicit consent
		// from the user (say, via a flag that defaults to false & uncaps range, explaining it may take some time).
		
		if ($num <= 1) return [];
		
		$factors = [];
		
		while ($num != 1) {
			if ($num % 2 == 0) {
				$factor = 2;
				goto factorFound;
			}
			
			for ($i = 3, $maxI = gmp_sqrt($num); $i <= $maxI; $i += 2) {
				if ($num % $i == 0) {
					$factor = $i;
					goto factorFound;
				}
			}
			
			$factor = $num;
			
			factorFound:
			
			$factors[] = gmp_strval($factor);
			$num /= $factor;
		}
		
		return $factors;
	}
}