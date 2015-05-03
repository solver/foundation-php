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
namespace Solver\Base;

/**
 * Wraps a single $_FILES entry into an object. See InputFromGlobals for automatically producing these out of a $_FILES
 * array.
 */
class UploadedFile {
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
	
	// Returns null if there's no error.
	public function getErrorCode() {
		if ($this->errorCode == 0) {
			return null;
		} else {
			return (int) $this->errorCode;
		}
	}
	
	// Returns null if there's no error.
	public function getErrorMessage() {
		switch ($this->errorCode) {
			case 0:
				return null;
				break;
			case 1:
				return 'The uploaded file exceeds the server-specified maximum allowed size.';
				break;
			case 2:
				return 'The uploaded file exceeds the client-specified maximum allowed size.';
				break;
			case 3:
				return 'The uploaded file was only partially uploaded.';
				break;
			case 4:
				return 'No file was uploaded.';
				break;
				
			// Not a mistake, there is no code 5.		
				
			case 6:
				return 'Missing a temporary folder.';
				break;
			case 7:
				return 'Failed to write file to disk.';
				break;
			case 8:
				return 'Forbidden file extension.';
				break;
			default:
				return 'Unknown file upload error.';
				break;
		}	
	}
	
	public function hasError() {
		return $this->getErrorCode() !== null;
	}
	
	public function getFilepath() {
		return $this->tempName;
	}
	
	public function getClientFilename() {
		return $this->clientName;
	}
	
	public function getClientMediaType() {
		return $this->clientType;
	}
	
	public function getSize() {
		return $this->size;
	}
	
//	TODO: This method is under consideration and might be removed permanently.
// 	public function toArray() {
// 		return [
// 			'tempName' => $this->tempName,
// 			'errorCode' => $this->errorCode,
// 			'size' => $this->size,
// 			'clientName' => $this->clientName,
// 			'clientType' => $this->clientType,	
// 		];
// 	}
}