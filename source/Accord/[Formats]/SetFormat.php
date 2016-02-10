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
 * A set is a dictionary that contains zero, one or more optional keys (from a predefined set) where the value is always
 * boolean true (or a value which is interpreted as boolean true, for example number 1, see BoolFormat).
 * 
 * TODO: Make this native, and don't delegate to DictFormat.
 * TODO: We error out on passing unknown values (right now we don't). Type algebra should be considered here, though.
 */
class SetFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
	protected $set = [];
	
	public function __construct(... $set) {
		if ($set) $this->set = $set;
	}
	
	public function add(... $set) {
		if ($set) array_push($this->set, ... $set);
	}
	
	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		$trueFmt = (new BoolFormat())->isTrue();
		
		$setFmt = new DictFormat();
		
		foreach ($this->set as $key) {
			$setFmt->addOptional($key, $trueFmt);
		}
		
		$setFmt->rejectRest();
		
		return $setFmt->fastApply($input, $output, $mask, $events, $path);
	}
}