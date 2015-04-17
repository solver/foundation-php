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
namespace Solver\Toolbox;

/**
 * Utilities for working with PHP arrays representing a list (i.e. indexed arrays).
 * 
 * A well-formed list is zero-based, dense (no "skipping" indexes), and homogenous (all items have the same type, even
 * if it may be a union type, i.e. one from a set of types). Length is arbitrary.
 * 
 * All methods here rely on the order of your indexes; the internal order of the array is ignored (it usually 
 * matches the indexes, but not always).
 */
class ListUtils {
	/**
	 * Returns the first element (index 0) of a list, or null if the list is empty.
	 * 
	 * This method exists to address a common scenario where you expect a list of zero or more non-null results and
	 * you need only the first one (or null). Common with database result sets and other similar interfaces.
	 * 
	 * @param array $list
	 */
	public static function first(array $list) {
		return $list ? $list[0] : null;
	}

	public static function last(array $list) {
		return $list ? $list[\count($list) - 1] : null;
	}
}