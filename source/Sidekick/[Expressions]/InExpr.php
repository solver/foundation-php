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

use Solver\Sql\SqlConnection;
use Solver\SqlX\SqlUtils;

class InExpr implements Expr {
	protected $valueList;
	
	public function __construct($valueList) {
		$this->valueList = $valueList;
	}
	
	public function getTransformed($transform) {
		$clone = clone $this;
		$clone->valueList = $transform($clone->valueList, false);
		return $clone;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \Solver\Sidekick\Expr::render()
	 */
	public function render(SqlConnection $conn, $columnName) {
		return SqlUtils::boolean($conn, [$columnName => ['IN', $this->valueList]]);
	}
}