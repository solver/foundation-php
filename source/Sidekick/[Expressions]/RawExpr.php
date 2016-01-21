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
 * This expression type simply renders the string given to the constructor. The subject parameter is ignored.
 * 
 * It's *highly* recommended to avoid raw expressions when using a preconfigured Sidekick as a repository. This class
 * is intended to be used in field handlers (Codec) and other handlers that belong strictly to the Sidekick
 * schema and configuration.
 */
class RawExpr implements Expr {
	protected $stringExpr;
	
	public function __construct($stringExpr) {
		$this->stringExpr = $stringExpr;	
	}
	
	function transformed($transform) {
		return $this;
	}
	
	function render(SqlContext $sqlContext, $subject) {
		return $this->stringExpr;
	}
}