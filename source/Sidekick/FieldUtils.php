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

// FIXME: Unfinished.
class FieldUtils {
	public static function requireAllFieldsExist($rowsIn, ...$fieldNames) {
		$count = count($fieldNames);
		
		// TODO: Can be optimized for 1...N fields, by unrolling inner loop.
		foreach ($rowsIn as $rowIn) {
			foreach ($fieldNames as $fieldName) {
				if (isset($rowIn[$fieldName]) || key_exists($fieldName, $rowIn)) $count++;
			}
		}
	}
	
	public static function requireAllFieldsExistOrNone($rowsIn, ...$fieldNames) {
		// TODO: Can be optimized for 1...N fields, by unrolling inner loop.
	}
}