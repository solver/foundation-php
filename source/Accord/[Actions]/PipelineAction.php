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
namespace Solver\Accord;

/**
 * Takes multiple actions and runs the value through each of them in order, feeding the output of one as the input of
 * the next one. This is analogous to function composition.
 * 
 * The pipeline processing is interrupted if any of the actions in the chain fails (i.e. throws ActionException from 
 * apply() or fastApply() returns false), and in this case the entire pipeline fails.
 */
class PipelineAction implements FastAction {
	use PipelineAny;

	public function __construct(Action ...$actions) {
		if ($actions) $this->actions = $actions;
	}
			
	/**
	 * Adds a new action to the pipeline.
	 * 
	 * @param Action $action 
	 * @return $this
	 */
	public function add(Action $action) {
		$this->actions[] = $action;
		return $this;
	}
}