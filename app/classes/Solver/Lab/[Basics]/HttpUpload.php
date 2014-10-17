<?php
namespace Solver\Shake;

/**
 * Wraps a single $_FILES entry into an object. See InputFromGlobals for automatically producing these out of a $_FILES
 * array.
 * 
 * @author Stan Vass
 * @copyright © 2012-2013 Solver Ltd. (http://www.solver.bg)
 * @license Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
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