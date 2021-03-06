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

// TODO: Document. This is a Format version of PipelineAction. See PipelineAction.
class PipelineFormat implements Format, FastAction {
	use PipelineAny;
	
	public function __construct(Format ...$formats) {
		if ($formats) $this->actions = $formats;
	}
	
	/**
	 * Adds a new format to the pipeline.
	 * 
	 * @param Format $format 
	 * @return $this
	 */
	public function add(Format $format) {
		$this->actions[] = $format;
		return $this;
	}
}