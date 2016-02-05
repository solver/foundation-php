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

// TODO: Document. This is a Transform version of PipelineAction. See PipelineAction.
class PipelineTransform implements Transform, FastAction {
	use PipelineAny;
	
	public function __construct(Transform ...$transforms) {
		if ($transforms) $this->actions = $transforms;
	}
			
	/**
	 * Adds a new transform to the pipeline.
	 * 
	 * @param Transform $transform 
	 * @return $this
	 */
	public function add(Transform $transform) {
		$this->actions[] = $transform;
		return $this;
	}
}