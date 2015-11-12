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

class FieldException extends \Exception {
	public static function throwMissingField($rowIndex, ...$fieldNames) {
		throw new self('One or more required fields are missing at row index ' . $rowIndex . ', field(s) ' . $this->fieldNames($fieldNames) . '.');
	}
	
	public static function throwBadField($expected, $rowIndex, ...$fieldNames) {
		throw new self('One or more fields have a bad format, ' . $expected . ' expected, at row index ' . $rowIndex . ', field(s) ' . $this->fieldNames($fieldNames) . '.');
	}
	
	public static function throwPartialFieldGroup($expected, $rowIndex, ...$fieldNames) {
		throw new self('All or none of the fields in group ' . $this->fieldNames($fieldNames) . ' should be given, some found, at row index ' . $rowIndex . ', field(s) ' . $this->fieldNames($fieldNames) . '.');
	}
	
	protected function fieldNames($fieldNames) {
		return '"' . implode('", "', $fieldNames) . '"';
	}
}