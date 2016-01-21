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
namespace Solver\Sidekick;

/**
 * Supports a set of basic comparison operators. A bit of a stand-in, until we have a more comprehensive solution for
 * building complex expressions in Sidekick. 
 */
class CompExpr implements Expr {
	protected $operator, $value, $many;
	
	protected static $operatorSet = [
		'=' => true,
		'==' => '=',
		'<>' => true,
		'!=' => '<>',
		'>' => true,
		'<' => true,
		'>=' => true,
		'<=' => true,
	];
	
	/**
	 * @param string $operator
	 * Comparison operator, one of:
	 * 
	 * - "=" or alias "=="
	 * - "<>" or alias "!="
	 * - ">"
	 * - "<"
	 * - ">="
	 * - "<="
	 * 
	 * @param mixed $value
	 * Value to compare to.
	 * 
	 * @param mixed ...$more
	 * Optionally you can specify more pairs of operator and value. If you do so, all comparisons will be combined in a
	 * single expression joined by boolean AND.
	 */
	public function __construct($operator, $value, ...$more) {
		$operator = $this->validateAndMapOperator($operator);
		
		if ($more) {
			$this->many = true;
			$this->operator = [$operator];
			$this->value = [$value];
			
			for ($i = 0, $m = count($more); $i < $m; $i += 2) {
				$this->operator[] = $this->validateAndMapOperator($more[$i]);
				$this->value[] = $more[$i + 1];
			}
		} else {
			$this->many = false;
			$this->operator = $operator;
			$this->value = $value;
		}
	}
	
	public function transformed($transform) {
		$clone = clone $this;
		
		if ($this->many) {
			foreach ($this->value as $i => $v) $clone->value[$i] = $transform($v);
		} else {
			$clone->value = $transform($this->value);
		}
		
		return $clone;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Expr::render()
	 */
	public function render(SqlContext $sqlContext, $subject) {
		// TODO: Can be optimized by not converting params to arrays here.
		// We also can save a bunch of parens in the output.
		if ($this->many) {
			$operators = $this->operator;
			$values = $this->value;
		} else {
			$operators = [$this->operator];
			$values = [$this->value];
		}
		
		$expr = [];
		foreach ($this->operator as $i => $op) {
			$expr[] = $subject . ' ' . $op . ' ' . $sqlContext->encodeValue($this->value[$i]);
		}
		
		return '((' . implode(') AND (', $expr) . '))';
	}
	
	protected function validateAndMapOperator($operator) {
		if (!isset(self::$operatorSet[$operator])) throw new \Exception('Bad operator.');
		
		// Map alises to valid SQL format.
		if (self::$operatorSet[$operator] !== true) $operator = self::$operatorSet[$operator];
	}
}