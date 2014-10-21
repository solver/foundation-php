<?php
/*
 * Copyright (C) 2011-2014 Solver Ltd. All rights reserved.
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
 * Wraps a single $_FILES entry into an object. See InputFromGlobals for automatically producing these out of a $_FILES
 * array.
 */
class HttpUpload {
	protected $tempName;
	protected $errorCode;
	protected $size;
	protected $clientName;	
	protected $clientType;
	
	public function __construct($tempName, $errorCode, $size, $clientName, $clientType) {
		$this->tempName = $tempName;
		$this->errorCode = $errorCode;
		$this->size = $size;
		$this->clientName = $clientName;
		$this->clientType = $clientType;
	}
	
	public function getTempName() {
		return $this->tempName;
	}
	
	public function getClientName() {
		return $this->clientName;
	}
}