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
 * A blank string is a string which is empty or contains only whitespace. This class is simply a shortcut for the 
 * following configuration:
 * 
 * <code>
 * $blankStringFormat = (new StringFormat)
 * 		->doTrim()
 * 		->isEmpty();
 * </code>
 * 
 * This is useful when a field can either be left blank, or have a specific format, as used with OrFormat:
 * 
 * <code>
 * $blankOrEmailFormat = (new OrFormat)
 * 		->add(new BlankStringFormat)
 * 		->add(new EmailAddressFormat);
 * </code>
 */
class BlankStringFormat extends StringFormat {
	public function __construct() {
		// TODO: Reimplement this via apply() for extra speed, instead of using StringFormat configuration?
		$this->trim()->isEmpty();
	}
}