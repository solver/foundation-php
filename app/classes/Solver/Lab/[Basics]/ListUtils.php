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
namespace Solver\Lab;

/**
 * Utilities for working with & processing PHP arrays representing a list (i.e. dense, 0-based, indexed arrays), where
 * all items have the same type (it may be a union type, i.e. one from a set of types).
 * 
 * If you pass a non-well formed list to these methods (such a "list" with holes or non-integer keys) the behavior is
 * undefined.
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
}