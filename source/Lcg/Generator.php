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
 * Implements a linear congruential generator, for scrambling numbers into stable pseudo-random numbers & back.
 * 
 * To emulate a classic pseudo-random sequence with an initial seed, pass your seed to advance(), then feed back the
 * resulting output as a seed for the next result. 
 * 
 * This class requires GMP for arbitrary precision integer math. This means there is no limit on the range of your
 * input and output numbers (numeric strings are used to pass in and return large integers).
 * 
 * TODO: Optimize implementation for ranges that fit into native int?
 */
class Generator {
	protected $config;
	
	/**
	 * @param mixed $config
	 * The output of ConfigBuilder::build(). 
	 * 
	 * IMPORTANT: Do not attempt to build the configuration directly (it's intentionally not documented here). Always
	 * use ConfigBuilder for producing the correct values (you can then var_export them and paste them in your code
	 * if you need better performance).
	 */
	public function __construct($config) {
		$this->config = [gmp_init($config[0]), gmp_init($config[1]), gmp_init($config[2]), gmp_init($config[3])];
	}
	
	/**
	 * @param int|string $value
	 * TODO
	 * 
	 * @return string
	 * TODO
	 */
	public function advance($value) {
		$value = gmp_init($value);
		list($period, $multiplier, $offset) = $this->config;
		if ($value < 0 || $value > $period) throw new \Exception('Parameter "value" doesn\'t satisfy constraint:  0 <= "value" < "period".');
		return gmp_strval(($multiplier * $value + $offset) % $period);	
	}
	
	/**
	 * @param int|string $value
	 * TODO
	 * 
	 * @return string
	 * TODO
	 */
	public function reverse($value) {
		$value = gmp_init($value);
		list($period, /* Multiplier not needed. */, $offset, $inverse) = $this->config;
		if ($value < 0 || $value > $period) throw new \Exception('Parameter "value" doesn\'t satisfy constraint:  0 <= "value" < "period".');
		return gmp_strval(($inverse * ($value - $offset)) % $period);	
	}
}