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
namespace Solver\AccordX;

use Solver\Accord\Format;
use Solver\Accord\FastAction;
use Solver\Accord\ApplyViaFastApply;
use Solver\Accord\ToValue;
use Solver\Logging\StatusLog as SL;
use Solver\Accord\InternalTransformUtils as ITU;

/**
 * This format falls in the group of "adapter" formats. Adapt one format to another, but still fulfill the full Format
 * contract (idempotence etc.).
 * 
 * Turns a string into a list based on a set of delimiter chars. The behavior of this particular class is optimized
 * for human-typed lists into a text field. If the input is already a list, it returns it unmodified.
 * 
 * This format is idempotent. If fed a list array instead of a string, it returns it unmodified.
 */
class StringToListFormat implements Format, FastAction {
	use ApplyViaFastApply;
	
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

	public function fastApply($input = null, & $output = null, $mask = 0, & $events = null, $path = null) {
		if (\is_array($input)) {
			$output = $input;
			return true;
		}
		
		elseif (\is_string($input)) {
			$list = \preg_split('@[' . $this->delimiters . ']+@', $input, 0, \PREG_SPLIT_NO_EMPTY);
			
			foreach ($list as & $item) {
				$item = \trim($item);
			}
			unset($item);
			
			return $list;
		} 
		
		else {
			if ($input instanceof ToValue) return $this->fastApply($input->toValue(), $output, $mask, $events, $path);
			
			if ($mask & SL::ERROR_FLAG) ITU::addErrorTo($events, $path, 'Please supply a string or a list.');
			$output = null;
			return false;
		}
	}
}