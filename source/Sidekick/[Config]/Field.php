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

interface Field {
}

interface InternalField extends Field {
	function render();
}

interface QueryField extends Field {
	function query($queryBuilder, $selectFields, $whereFields, $orderFields) {
		
	}
}

interface UpdateField extends Field {
	function update($updateBuilder, $setFields, $whereFields, $orderFields) {
		
	}
}

interface InsertField extends Field {
	function insert($insertBuilder, $insertFields) {
		
	}
}

interface DeleteField extends Field {
	function delete($deleteBuilder, $whereFields, $orderFields) {
		
	}
}

interface EncodedField {
	function encodeMany($fieldLists) {
		
	}
	
	function decodeMany($fieldLists) {
		
	}
}