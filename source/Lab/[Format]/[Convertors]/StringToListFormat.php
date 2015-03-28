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
 * This format falls in the group of "convertors". Simple formats which just take one kind of format and output another,
 * without having any support for customizable filters and tests.
 * 
 * Turns a string into a list based on a set of delimiter chars. The behavior of this particular class is optimized
 * for human-typed lists into a text field.
 * 
 * This format is idempotent. If fed a list array instead of a string, it returns it unmodified.
 */
class StringToListFormat implements Format {
	protected $delimiters;
	
	/**
	 * @param string $delimiters
	 * Optional (default = ',;\r\n'). One or more characters, either of which will act on its own as a delimiter, hence 
	 * a splitting point for creating items in a list. A sequence of multiple delimiter characters will not create an  
	 * empty element, neither will leading or trailing delimiter chars in the string.
	 */
	public function __construct($delimiters = ",;\r\n") {
		// Pre-escape for inclusion in regex.
		$this->delimiters = \preg_quote($delimiters, '@');
	}
	
	public function extract($value, ErrorLog $log, $path = null) {
		if (\is_array($value)) {
			return $value;
		}
		
		elseif (!\is_string($value)) {
			$log->addError($path, 'Please supply a string or a list.');
			return null;
		} 
		
		else {
			$list = \preg_split('@[' . $this->delimiters . ']+@', $value, 0, \PREG_SPLIT_NO_EMPTY);
			
			foreach ($list as & $item) {
				$item = \trim($item);
			}
			unset($item);
			
			return $list;
		}
	}
}